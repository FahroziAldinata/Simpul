<?php

namespace App\Http\Controllers;

use App\Enums\JenisMutasi;
use App\Http\Requests\Siswa\BatalkanMutasiRequest;
use App\Http\Requests\Siswa\StoreMutasiRequest;
use App\Models\MutasiSiswa;
use App\Models\Siswa;
use App\Services\MutasiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MutasiSiswaController extends Controller
{
    /**
     * Resolve student with strict tenant isolation (returns 404 if not found or cross-school).
     */
    private function resolveSiswa(Request $request, string $siswaId): Siswa
    {
        $sekolahId = $request->user()->hasRole('super_admin') ? session('sekolah_id') : $request->user()->sekolah_id;
        if (! $sekolahId) {
            abort(404, 'Sekolah tidak ditemukan.');
        }

        /** @var Siswa|null $siswa */
        $siswa = Siswa::withoutGlobalScopes()->withTrashed()->find($siswaId);
        if (! $siswa || $siswa->sekolah_id !== $sekolahId) {
            abort(404, 'Data siswa tidak ditemukan.');
        }

        return $siswa;
    }

    /**
     * Resolve mutation with strict tenant isolation (returns 404 if not found or cross-school).
     */
    private function resolveMutasi(Request $request, string $mutasiId): MutasiSiswa
    {
        $sekolahId = $request->user()->hasRole('super_admin') ? session('sekolah_id') : $request->user()->sekolah_id;
        if (! $sekolahId) {
            abort(404, 'Sekolah tidak ditemukan.');
        }

        /** @var MutasiSiswa|null $mutasi */
        $mutasi = MutasiSiswa::withoutGlobalScopes()->with(['siswa' => fn ($q) => $q->withTrashed(), 'semester'])->find($mutasiId);
        if (! $mutasi || $mutasi->sekolah_id !== $sekolahId) {
            abort(404, 'Data mutasi tidak ditemukan.');
        }

        return $mutasi;
    }

    /**
     * Get mutation history and class history for a student.
     */
    public function index(Request $request, string $siswaId): JsonResponse
    {
        $siswa = $this->resolveSiswa($request, $siswaId);
        Gate::authorize('viewMutasi', $siswa);

        $mutasi = $siswa->mutasi()
            ->with([
                'dariRombel:id,nama',
                'keRombel:id,nama',
                'semester:id,nama,is_aktif',
                'dibatalkanOleh:id,name',
            ])
            ->get();

        $riwayatKelas = $siswa->anggotaRombel()
            ->with([
                'rombel:id,nama,tingkat,wali_kelas_id',
                'rombel.waliKelas:id,nama',
                'semester:id,nama,is_aktif',
            ])
            ->join('semester', 'anggota_rombel.semester_id', '=', 'semester.id')
            ->orderBy('semester.tanggal_mulai', 'desc')
            ->select('anggota_rombel.*')
            ->get();

        return response()->json([
            'mutasi' => $mutasi,
            'riwayat_kelas' => $riwayatKelas,
            'status' => $siswa->status,
        ]);
    }

    /**
     * Store and execute a new mutation for the student.
     */
    public function store(StoreMutasiRequest $request, string $siswaId, MutasiService $mutasiService): RedirectResponse|JsonResponse
    {
        $siswa = $this->resolveSiswa($request, $siswaId);
        Gate::authorize('manageMutasi', $siswa);

        $validated = $request->validated();

        $mutasi = $mutasiService->executeMutasi(
            siswa: $siswa,
            tipe: JenisMutasi::from((string) $validated['tipe']),
            tanggal: (string) $validated['tanggal'],
            alasan: isset($validated['alasan']) ? (string) $validated['alasan'] : null,
            asalSekolah: isset($validated['asal_sekolah']) ? (string) $validated['asal_sekolah'] : null,
            sekolahTujuan: isset($validated['sekolah_tujuan']) ? (string) $validated['sekolah_tujuan'] : null,
            keRombelId: isset($validated['ke_rombel_id']) ? (string) $validated['ke_rombel_id'] : null,
            semesterId: isset($validated['semester_id']) ? (string) $validated['semester_id'] : null,
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Mutasi siswa berhasil dicatat.',
                'mutasi' => $mutasi,
            ], 201);
        }

        return back()->with('success', 'Mutasi siswa berhasil dicatat.');
    }

    /**
     * Cancel / revert an executed mutation.
     */
    public function batalkan(BatalkanMutasiRequest $request, string $mutasiId, MutasiService $mutasiService): RedirectResponse|JsonResponse
    {
        $mutasi = $this->resolveMutasi($request, $mutasiId);
        Gate::authorize('manageMutasi', $mutasi->siswa);

        $mutasiUpdated = $mutasiService->batalkanMutasi(
            mutasi: $mutasi,
            user: $request->user(),
            alasanBatal: (string) $request->validated('alasan_batal')
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Mutasi berhasil dibatalkan.',
                'mutasi' => $mutasiUpdated,
            ]);
        }

        return back()->with('success', 'Mutasi berhasil dibatalkan.');
    }
}
