<?php

namespace App\Http\Controllers;

use App\Enums\JenisMutasi;
use App\Enums\StatusSiswa;
use App\Exports\SiswaExport;
use App\Http\Requests\Siswa\BulkExportRequest;
use App\Http\Requests\Siswa\BulkRombelRequest;
use App\Http\Requests\Siswa\BulkStatusRequest;
use App\Models\Rombel;
use App\Models\Siswa;
use App\Services\MutasiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class SiswaAksiMassalController extends Controller
{
    public function __construct(
        protected MutasiService $mutasiService
    ) {}

    /**
     * Resolve active school id and ensure user has super_admin or operator role.
     */
    protected function authorizeAksiMassal(Request $request): string
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $activeSekolahId = $user->hasRole('super_admin') ? session('sekolah_id') : $user->sekolah_id;
        if (! $activeSekolahId) {
            abort(404, 'Sekolah aktif tidak ditemukan.');
        }

        if (! $user->hasRole(['super_admin', 'operator'])) {
            abort(403, 'Akses ditolak. Aksi massal hanya dapat dilakukan oleh Super Admin dan Operator.');
        }

        return (string) $activeSekolahId;
    }

    /**
     * Bulk move selected students to destination rombel.
     */
    public function ubahRombel(BulkRombelRequest $request): RedirectResponse|JsonResponse
    {
        $sekolahId = $this->authorizeAksiMassal($request);
        $validated = $request->validated();

        /** @var Rombel|null $targetRombel */
        $targetRombel = Rombel::with('semester')
            ->where('sekolah_id', $sekolahId)
            ->find($validated['rombel_id']);

        if (! $targetRombel) {
            throw ValidationException::withMessages([
                'rombel_id' => 'Rombel tujuan tidak ditemukan pada sekolah aktif.',
            ]);
        }

        /** @var array<string> $siswaIds */
        $siswaIds = $validated['siswa_ids'];
        $alasan = $validated['alasan'] ?? 'Aksi massal: Pindah rombel';

        // Transaction: All-or-nothing semantic
        DB::transaction(function () use ($siswaIds, $sekolahId, $targetRombel, $alasan) {
            foreach ($siswaIds as $siswaId) {
                /** @var Siswa|null $siswa */
                $siswa = Siswa::withoutGlobalScopes()->find($siswaId);

                if (! $siswa || $siswa->sekolah_id !== $sekolahId) {
                    throw ValidationException::withMessages([
                        'siswa_ids' => "Gagal memproses siswa (ID: {$siswaId}): Siswa tidak ditemukan atau bukan milik sekolah aktif.",
                    ]);
                }

                try {
                    $this->mutasiService->executeMutasi(
                        siswa: $siswa,
                        tipe: JenisMutasi::PindahRombel,
                        tanggal: now()->toDateString(),
                        alasan: $alasan,
                        keRombelId: $targetRombel->id
                    );
                } catch (ValidationException $e) {
                    $firstError = collect($e->errors())->flatten()->first() ?? $e->getMessage();
                    throw ValidationException::withMessages([
                        'siswa_ids' => "Gagal memproses siswa {$siswa->nama}: {$firstError}",
                    ]);
                } catch (Throwable $e) {
                    throw ValidationException::withMessages([
                        'siswa_ids' => "Gagal memproses siswa {$siswa->nama}: {$e->getMessage()}",
                    ]);
                }
            }
        });

        // Quota check: over-quota is a warning, not a blocker
        $targetRombel->loadCount([
            'anggotaRombel as jumlah_siswa' => fn ($q) => $q->whereHas('siswa', fn ($s) => $s->where('status', StatusSiswa::Aktif)),
        ]);
        /** @var int $jumlahSiswa */
        $jumlahSiswa = (int) $targetRombel->getAttribute('jumlah_siswa');
        $warning = null;
        if ($targetRombel->kuota > 0 && $jumlahSiswa > $targetRombel->kuota) {
            $warning = "Rombel {$targetRombel->nama} kini melebihi kuota ({$jumlahSiswa}/{$targetRombel->kuota} siswa).";
        }

        $message = 'Berhasil memindahkan '.count($siswaIds)." siswa ke rombel {$targetRombel->nama}.";

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'warning' => $warning,
            ]);
        }

        $redirect = back()->with('success', $message);
        if ($warning) {
            $redirect->with('warning', $warning);
        }

        return $redirect;
    }

    /**
     * Bulk change status of selected students.
     */
    public function ubahStatus(BulkStatusRequest $request): RedirectResponse|JsonResponse
    {
        $sekolahId = $this->authorizeAksiMassal($request);
        $validated = $request->validated();

        $tipe = match ($validated['status']) {
            'keluar', 'mutasi_keluar', 'pindah' => JenisMutasi::Keluar,
            'lulus' => JenisMutasi::Lulus,
            'drop_out', 'do' => JenisMutasi::DropOut,
            default => throw ValidationException::withMessages(['status' => 'Status tidak didukung.']),
        };

        /** @var array<string> $siswaIds */
        $siswaIds = $validated['siswa_ids'];
        $tanggal = $validated['tanggal'] ?? now()->toDateString();
        $alasan = $validated['alasan'] ?? 'Aksi massal perubahan status';
        $sekolahTujuan = $validated['sekolah_tujuan'] ?? null;

        // Transaction: All-or-nothing semantic
        DB::transaction(function () use ($siswaIds, $sekolahId, $tipe, $tanggal, $alasan, $sekolahTujuan) {
            foreach ($siswaIds as $siswaId) {
                /** @var Siswa|null $siswa */
                $siswa = Siswa::withoutGlobalScopes()->find($siswaId);

                if (! $siswa || $siswa->sekolah_id !== $sekolahId) {
                    throw ValidationException::withMessages([
                        'siswa_ids' => "Gagal memproses siswa (ID: {$siswaId}): Siswa tidak ditemukan atau bukan milik sekolah aktif.",
                    ]);
                }

                try {
                    $this->mutasiService->executeMutasi(
                        siswa: $siswa,
                        tipe: $tipe,
                        tanggal: $tanggal,
                        alasan: $alasan,
                        sekolahTujuan: $sekolahTujuan
                    );
                } catch (ValidationException $e) {
                    $firstError = collect($e->errors())->flatten()->first() ?? $e->getMessage();
                    throw ValidationException::withMessages([
                        'siswa_ids' => "Gagal memproses siswa {$siswa->nama}: {$firstError}",
                    ]);
                } catch (Throwable $e) {
                    throw ValidationException::withMessages([
                        'siswa_ids' => "Gagal memproses siswa {$siswa->nama}: {$e->getMessage()}",
                    ]);
                }
            }
        });

        $statusLabel = ucfirst($tipe->value);
        $message = 'Berhasil memperbarui status '.count($siswaIds)." siswa menjadi {$statusLabel}.";

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Export selected or filtered students to Excel.
     */
    public function ekspor(BulkExportRequest $request): BinaryFileResponse|RedirectResponse
    {
        $sekolahId = $this->authorizeAksiMassal($request);
        $validated = $request->validated();

        $query = Siswa::where('sekolah_id', $sekolahId)
            ->with(['anggotaRombelAktif.rombel']);

        if (! empty($validated['siswa_ids'])) {
            $query->whereIn('id', $validated['siswa_ids']);
        } else {
            if (! empty($validated['status'])) {
                $query->where('status', $validated['status']);
            }
            if (! empty($validated['rombel_id'])) {
                $query->whereHas('anggotaRombelAktif', fn ($q) => $q->where('rombel_id', $validated['rombel_id']));
            }
            if (! empty($validated['search'])) {
                $search = (string) $validated['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('nama', 'like', "%{$search}%")
                        ->orWhere('nisn', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%");
                });
            }
        }

        $students = $query->orderBy('nama')->get();

        if ($students->isEmpty()) {
            return back()->with('error', 'Tidak ada data siswa yang dapat diekspor.');
        }

        // Record to activity_log (Audit metadata: who, how many rows, when)
        activity('siswa')
            ->causedBy($request->user())
            ->withProperties([
                'jumlah_siswa' => $students->count(),
                'format' => 'xlsx',
            ])
            ->log("Mengekspor {$students->count()} data siswa ke Excel");

        $filename = 'data-siswa-'.now()->format('Ymd_His').'.xlsx';

        /** @var BinaryFileResponse $response */
        $response = Excel::download(new SiswaExport($students), $filename);

        return $response;
    }
}
