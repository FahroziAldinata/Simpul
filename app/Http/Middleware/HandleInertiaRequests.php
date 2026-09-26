<?php

namespace App\Http\Middleware;

use App\Models\Sekolah;
use App\Models\Semester;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $sekolahId = session('sekolah_id') ?? $user?->sekolah_id;
        $periode = null;

        if ($sekolahId) {
            $semesters = Semester::where('sekolah_id', $sekolahId)
                ->with('tahunAjaran')
                ->orderByDesc('tanggal_mulai')
                ->get();

            $selectedSemesterId = session('selected_semester_id');
            $currentSemester = $semesters->firstWhere('id', $selectedSemesterId)
                ?? $semesters->firstWhere('is_aktif', true)
                ?? $semesters->first();

            if ($currentSemester && ! $selectedSemesterId) {
                session(['selected_semester_id' => $currentSemester->id]);
            }

            $periode = [
                'selected_semester_id' => $currentSemester?->id,
                'semester_nama' => $currentSemester?->nama,
                'tahun_ajaran_nama' => $currentSemester?->tahunAjaran?->nama,
                'is_aktif' => $currentSemester ? (bool) $currentSemester->is_aktif : true,
                'daftar_semester' => $semesters->map(fn ($s) => [
                    'id' => $s->id,
                    'label' => "TA {$s->tahunAjaran?->nama} - Semester {$s->nama}".($s->is_aktif ? ' (Aktif)' : ''),
                    'is_aktif' => (bool) $s->is_aktif,
                ])->values()->all(),
            ];
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'roles' => $user?->getRoleNames() ?? [],
                'permissions' => $user?->getAllPermissions()->pluck('name') ?? [],
                'sekolah_aktif_id' => session('sekolah_id'),
                'daftar_sekolah' => ($user && $user->sekolah_id === null)
                    ? Sekolah::orderBy('npsn')->get(['id', 'nama', 'npsn'])->toArray()
                    : [],
            ],
            'periode' => $periode,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
