<?php

namespace App\Http\Controllers;

use App\Http\Requests\Pegawai\StorePegawaiRequest;
use App\Http\Requests\Pegawai\UpdatePegawaiRequest;
use App\Models\Pegawai;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PegawaiController extends Controller
{
    /**
     * Resolve employee with strict tenant isolation (returns 404 if not found or cross-school).
     */
    private function resolvePegawai(Request $request, string $pegawaiId): Pegawai
    {
        $sekolahId = $request->user()->hasRole('super_admin') ? session('sekolah_id') : $request->user()->sekolah_id;
        if (! $sekolahId) {
            abort(404, 'Sekolah tidak ditemukan.');
        }

        /** @var Pegawai|null $pegawai */
        $pegawai = Pegawai::withoutGlobalScopes()->with(['user'])->find($pegawaiId);
        if (! $pegawai || $pegawai->sekolah_id !== $sekolahId) {
            abort(404, 'Data pegawai tidak ditemukan.');
        }

        return $pegawai;
    }

    /**
     * Display a listing of employees.
     */
    public function index(Request $request): Response|JsonResponse
    {
        Gate::authorize('viewAny', Pegawai::class);

        $sekolahId = $request->user()->hasRole('super_admin') ? session('sekolah_id') : $request->user()->sekolah_id;
        $semesterAktif = Semester::where('sekolah_id', $sekolahId)->where('is_aktif', true)->first();

        $query = Pegawai::query()
            ->with([
                'user' => fn ($q) => $q->select('id', 'name', 'email', 'must_change_password')->with('roles:id,name'),
                'alokasiJamMapel' => function ($q) use ($semesterAktif) {
                    if ($semesterAktif) {
                        $q->whereHas('rombel', fn ($rq) => $rq->where('semester_id', $semesterAktif->id));
                    }
                },
            ]);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'ilike', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhere('nuptk', 'like', "%{$search}%");
            });
        }

        // Filter: jenis
        if ($jenis = $request->input('jenis')) {
            $query->where('jenis', $jenis);
        }

        // Filter: status_kepegawaian
        if ($status = $request->input('status_kepegawaian')) {
            $query->where('status_kepegawaian', $status);
        }

        // Sort
        $sortBy = (string) $request->input('sort_by', 'nama');
        $sortOrder = (string) $request->input('sort_order', 'asc');
        $allowedSorts = ['nama', 'nip', 'jenis', 'status_kepegawaian', 'jam_maks_per_minggu'];

        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('nama', 'asc');
        }

        $perPage = (int) $request->input('per_page', 25);
        $pegawaiList = $query->paginate($perPage)->withQueryString();

        $pegawaiList->through(function (Pegawai $p) {
            $p->setAttribute('beban_mengajar_aktual', $p->alokasiJamMapel->sum('jam_per_minggu'));
            $p->setAttribute('roles', $p->user ? $p->user->roles->pluck('name')->values()->all() : []);

            return $p;
        });

        if ($request->wantsJson()) {
            return response()->json($pegawaiList);
        }

        return Inertia::render('pegawai/Index', [
            'pegawai' => $pegawaiList,
            'currentSemester' => $semesterAktif ? [
                'id' => $semesterAktif->id,
                'nama' => $semesterAktif->nama,
            ] : null,
            'filters' => [
                'search' => $request->input('search', ''),
                'jenis' => $request->input('jenis', ''),
                'status_kepegawaian' => $request->input('status_kepegawaian', ''),
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
                'per_page' => $perPage,
            ],
            'canManage' => $request->user()->can('pegawai.create'),
        ]);
    }

    /**
     * Store a newly created employee and auto-generate user account.
     */
    public function store(StorePegawaiRequest $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', Pegawai::class);

        $sekolahId = $request->user()->hasRole('super_admin') ? session('sekolah_id') : $request->user()->sekolah_id;
        if (! $sekolahId) {
            abort(404, 'Sekolah aktif tidak ditemukan.');
        }

        $validated = $request->validated();

        $result = DB::transaction(function () use ($validated, $sekolahId) {
            // Auto-create random initial password for user
            $plainPassword = Str::password(10, letters: true, numbers: true, symbols: false);

            /** @var User $user */
            $user = User::create([
                'name' => $validated['nama'],
                'email' => $validated['email'],
                'password' => Hash::make($plainPassword),
                'sekolah_id' => $sekolahId,
                'nip' => $validated['nip'] ?? null,
                'nama_lengkap' => $validated['nama'],
                'must_change_password' => true,
            ]);

            // Assign standard role based on jenis pegawai + optional additional roles
            $baseRole = match ($validated['jenis']) {
                'guru' => 'guru',
                'tu' => 'operator',
                'kepsek' => 'kepsek',
                default => 'guru',
            };
            $additionalRoles = $validated['roles'] ?? [];
            $rolesToSync = array_values(array_unique(array_merge([$baseRole], $additionalRoles)));
            $user->syncRoles($rolesToSync);

            /** @var Pegawai $pegawai */
            $pegawai = Pegawai::create([
                'sekolah_id' => $sekolahId,
                'user_id' => $user->id,
                'nip' => $validated['nip'] ?? null,
                'nuptk' => $validated['nuptk'] ?? null,
                'nama' => $validated['nama'],
                'jenis' => $validated['jenis'],
                'status_kepegawaian' => $validated['status_kepegawaian'],
                'jenis_kelamin' => $validated['jenis_kelamin'] ?? null,
                'tempat_lahir' => $validated['tempat_lahir'] ?? null,
                'tanggal_lahir' => $validated['tanggal_lahir'] ?? null,
                'agama' => $validated['agama'] ?? null,
                'alamat' => $validated['alamat'] ?? null,
                'no_hp' => $validated['no_hp'] ?? null,
                'email' => $validated['email'],
                'jam_maks_per_minggu' => $validated['jam_maks_per_minggu'] ?? 24,
                'hari_tidak_mengajar' => $validated['hari_tidak_mengajar'] ?? [],
            ]);

            return [
                'pegawai' => $pegawai,
                'user' => $user,
                'plainPassword' => $plainPassword,
            ];
        });

        // Flash initial password to session (kata sandi awal sekali tampil)
        session()->flash('initial_account', [
            'nama' => $result['pegawai']->nama,
            'email' => $result['user']->email,
            'password' => $result['plainPassword'],
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Data pegawai dan akun pengguna berhasil dibuat.',
                'pegawai' => $result['pegawai'],
                'initial_password' => $result['plainPassword'],
            ], 201);
        }

        return redirect()->back()->with('success', 'Data pegawai dan akun pengguna berhasil dibuat.');
    }

    /**
     * Display the specified employee.
     */
    public function show(Request $request, string $pegawaiId): JsonResponse
    {
        $pegawai = $this->resolvePegawai($request, $pegawaiId);
        Gate::authorize('view', $pegawai);

        $pegawai->load(['user.roles:id,name', 'alokasiJamMapel.rombel', 'alokasiJamMapel.mataPelajaran']);
        $pegawai->setAttribute('roles', $pegawai->user ? $pegawai->user->roles->pluck('name')->values()->all() : []);

        return response()->json($pegawai);
    }

    /**
     * Update the specified employee.
     */
    public function update(UpdatePegawaiRequest $request, string $pegawaiId): RedirectResponse|JsonResponse
    {
        $pegawai = $this->resolvePegawai($request, $pegawaiId);
        Gate::authorize('update', $pegawai);

        $validated = $request->validated();

        DB::transaction(function () use ($pegawai, $validated) {
            $pegawai->update([
                'nip' => $validated['nip'] ?? null,
                'nuptk' => $validated['nuptk'] ?? null,
                'nama' => $validated['nama'],
                'jenis' => $validated['jenis'],
                'status_kepegawaian' => $validated['status_kepegawaian'],
                'jenis_kelamin' => $validated['jenis_kelamin'] ?? null,
                'tempat_lahir' => $validated['tempat_lahir'] ?? null,
                'tanggal_lahir' => $validated['tanggal_lahir'] ?? null,
                'agama' => $validated['agama'] ?? null,
                'alamat' => $validated['alamat'] ?? null,
                'no_hp' => $validated['no_hp'] ?? null,
                'email' => $validated['email'],
                'jam_maks_per_minggu' => $validated['jam_maks_per_minggu'] ?? 24,
                'hari_tidak_mengajar' => $validated['hari_tidak_mengajar'] ?? [],
            ]);

            // Sync user data & roles
            if ($pegawai->user) {
                $pegawai->user->update([
                    'name' => $validated['nama'],
                    'nama_lengkap' => $validated['nama'],
                    'email' => $validated['email'],
                    'nip' => $validated['nip'] ?? null,
                ]);

                $baseRole = match ($validated['jenis']) {
                    'guru' => 'guru',
                    'tu' => 'operator',
                    'kepsek' => 'kepsek',
                    default => 'guru',
                };
                $additionalRoles = $validated['roles'] ?? [];
                $rolesToSync = array_values(array_unique(array_merge([$baseRole], $additionalRoles)));
                $pegawai->user->syncRoles($rolesToSync);
            }
        });

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Data pegawai berhasil diperbarui.',
                'pegawai' => $pegawai->fresh(),
            ]);
        }

        return redirect()->back()->with('success', 'Data pegawai berhasil diperbarui.');
    }

    /**
     * Remove the specified employee from storage.
     */
    public function destroy(Request $request, string $pegawaiId): RedirectResponse|JsonResponse
    {
        $pegawai = $this->resolvePegawai($request, $pegawaiId);
        Gate::authorize('delete', $pegawai);

        DB::transaction(function () use ($pegawai) {
            $user = $pegawai->user;
            $pegawai->delete();
            if ($user) {
                $user->delete();
            }
        });

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Data pegawai berhasil dihapus.',
            ]);
        }

        return redirect()->back()->with('success', 'Data pegawai berhasil dihapus.');
    }
}
