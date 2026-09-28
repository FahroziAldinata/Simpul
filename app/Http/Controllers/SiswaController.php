<?php

namespace App\Http\Controllers;

use App\Http\Requests\Siswa\StoreSiswaRequest;
use App\Http\Requests\Siswa\UpdateSiswaRequest;
use App\Http\Requests\Siswa\UpdateSiswaWaliKelasRequest;
use App\Models\AnggotaRombel;
use App\Models\Rombel;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\WaliSiswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SiswaController extends Controller
{
    /**
     * Display a listing of the students.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Siswa::class);

        $user = $request->user();
        $sekolahId = $user->hasRole('super_admin') ? session('sekolah_id') : $user->sekolah_id;

        $activeSemester = Semester::where('is_aktif', true)->first();

        $query = Siswa::query()
            ->with([
                'wali',
                'anggotaRombelAktif.rombel',
            ]);

        // Scoping khusus Wali Kelas: hanya siswa di rombel perwaliannya
        if ($user->hasRole('wali_kelas') && ! $user->hasRole(['super_admin', 'operator', 'kepsek', 'waka_kurikulum'])) {
            $pegawaiId = $user->pegawai?->id;
            $query->whereHas('anggotaRombel', function ($q) use ($pegawaiId) {
                $q->whereHas('rombel', function ($r) use ($pegawaiId) {
                    $r->where('wali_kelas_id', $pegawaiId);
                })->whereHas('semester', function ($s) {
                    $s->where('is_aktif', true);
                });
            });
        }

        // Global Search (Indexed by DB: nama, nisn, nik)
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'ilike', "%{$search}%")
                    ->orWhere('nisn', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%");
            });
        }

        // Filter: Rombel
        if ($rombelId = $request->input('rombel_id')) {
            $query->whereHas('anggotaRombel', function ($q) use ($rombelId) {
                $q->where('rombel_id', $rombelId)
                    ->whereHas('semester', function ($s) {
                        $s->where('is_aktif', true);
                    });
            });
        }

        // Filter: Tingkat
        if ($tingkat = $request->input('tingkat')) {
            $query->whereHas('anggotaRombel', function ($q) use ($tingkat) {
                $q->whereHas('rombel', function ($r) use ($tingkat) {
                    $r->where('tingkat', (int) $tingkat);
                })->whereHas('semester', function ($s) {
                    $s->where('is_aktif', true);
                });
            });
        }

        // Filter: Jenis Kelamin
        if ($gender = $request->input('jenis_kelamin')) {
            $query->where('jenis_kelamin', $gender);
        }

        // Filter: Status Siswa
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter: Kelengkapan Data (Menggunakan SQL Scope, Bebas N+1)
        if ($kelengkapan = $request->input('kelengkapan')) {
            if ($kelengkapan === 'lengkap') {
                $query->dataLengkap();
            } elseif ($kelengkapan === 'belum_lengkap') {
                $query->dataBelumLengkap();
            }
        }

        // Sorting
        $allowedSorts = ['nama', 'nisn', 'nik', 'status', 'created_at'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true) ? $request->input('sort_by') : 'nama';
        $sortOrder = $request->input('sort_order') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortOrder);

        // Pagination (25, 50, 100)
        $allowedPerPage = [25, 50, 100];
        $perPage = in_array((int) $request->input('per_page'), $allowedPerPage, true) ? (int) $request->input('per_page') : 25;
        $siswa = $query->paginate($perPage)->withQueryString();

        // Rombel List untuk filter
        $rombelList = Rombel::query()
            ->when($activeSemester, fn ($q) => $q->where('semester_id', $activeSemester->id))
            ->orderBy('tingkat')
            ->orderBy('nama')
            ->get(['id', 'nama', 'tingkat']);

        return Inertia::render('siswa/Index', [
            'siswa' => $siswa,
            'rombelList' => $rombelList,
            'currentSemester' => $activeSemester ? [
                'id' => $activeSemester->id,
                'nama' => $activeSemester->nama,
                'is_aktif' => (bool) $activeSemester->is_aktif,
            ] : null,
            'filters' => [
                'search' => $request->input('search', ''),
                'rombel_id' => $request->input('rombel_id', ''),
                'tingkat' => $request->input('tingkat', ''),
                'jenis_kelamin' => $request->input('jenis_kelamin', ''),
                'status' => $request->input('status', ''),
                'kelengkapan' => $request->input('kelengkapan', ''),
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
                'per_page' => $perPage,
            ],
            'userPreferences' => $user->preferences['siswa_columns'] ?? null,
            'canManage' => (bool) ($user->hasRole(['super_admin', 'operator']) || $user->can('siswa.create')),
            'isWaliKelas' => (bool) ($user->hasRole('wali_kelas') && ! $user->hasRole(['super_admin', 'operator'])),
        ]);
    }

    /**
     * Store a newly created student.
     */
    public function store(StoreSiswaRequest $request): RedirectResponse
    {
        Gate::authorize('create', Siswa::class);

        $validated = $request->validated();
        $sekolahId = $request->user()->hasRole('super_admin') ? session('sekolah_id') : $request->user()->sekolah_id;

        DB::transaction(function () use ($validated, $sekolahId) {
            /** @var Siswa $siswa */
            $siswa = Siswa::create([
                'sekolah_id' => $sekolahId,
                'nisn' => $validated['nisn'],
                'nik' => $validated['nik'],
                'nama' => $validated['nama'],
                'jenis_kelamin' => $validated['jenis_kelamin'],
                'tempat_lahir' => $validated['tempat_lahir'],
                'tanggal_lahir' => $validated['tanggal_lahir'],
                'agama' => $validated['agama'],
                'alamat' => $validated['alamat'] ?? null,
                'no_hp' => $validated['no_hp'] ?? null,
                'status' => $validated['status'],
            ]);

            // Save Guardian(s)
            if (! empty($validated['wali'])) {
                foreach ($validated['wali'] as $w) {
                    if (! empty($w['nama'])) {
                        WaliSiswa::create([
                            'sekolah_id' => $sekolahId,
                            'siswa_id' => $siswa->id,
                            'hubungan' => $w['hubungan'] ?? 'wali',
                            'nama' => $w['nama'],
                            'pekerjaan' => $w['pekerjaan'] ?? null,
                            'no_hp' => $w['no_hp'] ?? null,
                            'alamat' => $w['alamat'] ?? null,
                        ]);
                    }
                }
            }

            // Assign Rombel if provided
            if (! empty($validated['rombel_id'])) {
                $activeSemester = Semester::where('is_aktif', true)->first();
                if ($activeSemester) {
                    AnggotaRombel::create([
                        'sekolah_id' => $sekolahId,
                        'rombel_id' => $validated['rombel_id'],
                        'siswa_id' => $siswa->id,
                        'semester_id' => $activeSemester->id,
                        'nomor_absen' => $validated['nomor_absen'] ?? null,
                    ]);
                }
            }
        });

        return back()->with('success', 'Data siswa berhasil ditambahkan.');
    }

    /**
     * Update the specified student in storage.
     */
    public function update(Request $request, Siswa $siswa): RedirectResponse
    {
        $user = $request->user();
        Gate::authorize('update', $siswa);

        // Jika Wali Kelas (RU terbatas rombelnya)
        if ($user->hasRole('wali_kelas') && ! $user->hasRole(['super_admin', 'operator'])) {
            $formRequest = app(UpdateSiswaWaliKelasRequest::class);
            $validated = $formRequest->validated();

            DB::transaction(function () use ($siswa, $validated) {
                $siswa->update([
                    'alamat' => $validated['alamat'] ?? null,
                    'no_hp' => $validated['no_hp'] ?? null,
                ]);

                if (isset($validated['wali'])) {
                    foreach ($validated['wali'] as $w) {
                        if (! empty($w['id'])) {
                            WaliSiswa::where('id', $w['id'])->where('siswa_id', $siswa->id)->update([
                                'nama' => $w['nama'] ?? '',
                                'hubungan' => $w['hubungan'] ?? 'wali',
                                'pekerjaan' => $w['pekerjaan'] ?? null,
                                'no_hp' => $w['no_hp'] ?? null,
                                'alamat' => $w['alamat'] ?? null,
                            ]);
                        } elseif (! empty($w['nama'])) {
                            WaliSiswa::create([
                                'sekolah_id' => $siswa->sekolah_id,
                                'siswa_id' => $siswa->id,
                                'hubungan' => $w['hubungan'] ?? 'wali',
                                'nama' => $w['nama'],
                                'pekerjaan' => $w['pekerjaan'] ?? null,
                                'no_hp' => $w['no_hp'] ?? null,
                                'alamat' => $w['alamat'] ?? null,
                            ]);
                        }
                    }
                }
            });

            return back()->with('success', 'Data kontak dan wali siswa berhasil diperbarui oleh Wali Kelas.');
        }

        // Super Admin & Operator (Full update)
        $formRequest = app(UpdateSiswaRequest::class);
        $validated = $formRequest->validated();

        DB::transaction(function () use ($siswa, $validated) {
            $siswa->update([
                'nisn' => $validated['nisn'],
                'nik' => $validated['nik'],
                'nama' => $validated['nama'],
                'jenis_kelamin' => $validated['jenis_kelamin'],
                'tempat_lahir' => $validated['tempat_lahir'],
                'tanggal_lahir' => $validated['tanggal_lahir'],
                'agama' => $validated['agama'],
                'alamat' => $validated['alamat'] ?? null,
                'no_hp' => $validated['no_hp'] ?? null,
                'status' => $validated['status'],
            ]);

            // Sync Wali
            if (isset($validated['wali'])) {
                foreach ($validated['wali'] as $w) {
                    if (! empty($w['id'])) {
                        WaliSiswa::where('id', $w['id'])->where('siswa_id', $siswa->id)->update([
                            'nama' => $w['nama'] ?? '',
                            'hubungan' => $w['hubungan'] ?? 'wali',
                            'pekerjaan' => $w['pekerjaan'] ?? null,
                            'no_hp' => $w['no_hp'] ?? null,
                            'alamat' => $w['alamat'] ?? null,
                        ]);
                    } elseif (! empty($w['nama'])) {
                        WaliSiswa::create([
                            'sekolah_id' => $siswa->sekolah_id,
                            'siswa_id' => $siswa->id,
                            'hubungan' => $w['hubungan'] ?? 'wali',
                            'nama' => $w['nama'],
                            'pekerjaan' => $w['pekerjaan'] ?? null,
                            'no_hp' => $w['no_hp'] ?? null,
                            'alamat' => $w['alamat'] ?? null,
                        ]);
                    }
                }
            }

            // Sync Rombel aktif jika semester aktif
            $activeSemester = Semester::where('is_aktif', true)->first();
            if ($activeSemester && array_key_exists('rombel_id', $validated)) {
                if (! empty($validated['rombel_id'])) {
                    AnggotaRombel::updateOrCreate(
                        [
                            'siswa_id' => $siswa->id,
                            'semester_id' => $activeSemester->id,
                        ],
                        [
                            'sekolah_id' => $siswa->sekolah_id,
                            'rombel_id' => $validated['rombel_id'],
                            'nomor_absen' => $validated['nomor_absen'] ?? null,
                        ]
                    );
                } else {
                    AnggotaRombel::where('siswa_id', $siswa->id)
                        ->where('semester_id', $activeSemester->id)
                        ->delete();
                }
            }
        });

        return back()->with('success', 'Data siswa berhasil diperbarui.');
    }

    /**
     * Remove the specified student from storage.
     */
    public function destroy(Siswa $siswa): RedirectResponse
    {
        Gate::authorize('delete', $siswa);

        $siswa->delete();

        return back()->with('success', 'Data siswa berhasil dihapus.');
    }

    /**
     * Check global availability of NISN across all schools (Rate limited, authorized).
     */
    public function checkNisn(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && ($user->can('siswa.create') || $user->can('siswa.update')), 403, 'Akses ditolak.');

        $validated = $request->validate([
            'nisn' => ['required', 'string', 'size:10', 'regex:/^[0-9]{10}$/'],
            'except_id' => ['nullable', 'uuid'],
        ]);

        $exists = Siswa::withoutGlobalScopes()
            ->where('nisn', $validated['nisn'])
            ->whereNull('deleted_at')
            ->when(! empty($validated['except_id']), fn ($q) => $q->where('id', '!=', $validated['except_id']))
            ->exists();

        return response()->json([
            'available' => ! $exists,
            'message' => $exists ? 'NISN sudah terdaftar di sistem.' : 'NISN tersedia.',
        ]);
    }
}
