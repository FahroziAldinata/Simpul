<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoveJadwalRequest;
use App\Http\Requests\StoreJadwalRequest;
use App\Http\Requests\UpdateJadwalRequest;
use App\Models\AlokasiJamMapel;
use App\Models\JadwalPelajaran;
use App\Models\JamKerja;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Rombel;
use App\Models\Ruang;
use App\Models\Semester;
use App\Services\Jadwal\ConflictDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class JadwalController extends Controller
{
    private function resolveSekolahId(): string
    {
        $sekolahId = session('sekolah_id') ?? auth()->user()?->sekolah_id;
        if (! $sekolahId) {
            abort(404, 'Sekolah tidak ditemukan.');
        }

        return (string) $sekolahId;
    }

    private function resolveCurrentSemester(string $sekolahId, ?string $semesterId = null): ?Semester
    {
        if ($semesterId) {
            $semester = Semester::where('sekolah_id', $sekolahId)->where('id', $semesterId)->with('tahunAjaran')->first();
            if ($semester) {
                return $semester;
            }
        }

        $selectedId = session('selected_semester_id');
        if ($selectedId) {
            $semester = Semester::where('sekolah_id', $sekolahId)->where('id', $selectedId)->with('tahunAjaran')->first();
            if ($semester) {
                return $semester;
            }
        }

        return Semester::where('sekolah_id', $sekolahId)->where('is_aktif', true)->with('tahunAjaran')->first()
            ?? Semester::where('sekolah_id', $sekolahId)->with('tahunAjaran')->orderByDesc('tanggal_mulai')->first();
    }

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', JadwalPelajaran::class);

        $sekolahId = $this->resolveSekolahId();
        $semesters = Semester::where('sekolah_id', $sekolahId)->with('tahunAjaran')->orderByDesc('tanggal_mulai')->get();
        $selectedSemester = $this->resolveCurrentSemester($sekolahId, $request->query('semester_id'));

        $rombels = [];
        $jadwals = [];
        $ringkasan = [];
        $alokasiList = [];

        if ($selectedSemester) {
            $rombels = Rombel::where('sekolah_id', $sekolahId)
                ->where('semester_id', $selectedSemester->id)
                ->orderBy('tingkat')
                ->orderBy('nama')
                ->get();

            $jadwals = JadwalPelajaran::with(['guru', 'rombel', 'ruang', 'mataPelajaran'])
                ->where('sekolah_id', $sekolahId)
                ->where('semester_id', $selectedSemester->id)
                ->orderBy('hari')
                ->orderBy('jam_mulai_ke')
                ->get();

            $alokasiList = AlokasiJamMapel::with(['mataPelajaran', 'guru', 'rombel'])
                ->where('sekolah_id', $sekolahId)
                ->whereIn('rombel_id', $rombels->pluck('id'))
                ->get();

            // Hitung ringkasan kelengkapan kurikulum per rombel (H4)
            foreach ($rombels as $rombel) {
                $alokasiRombel = $alokasiList->where('rombel_id', $rombel->id);
                $jadwalRombel = $jadwals->where('rombel_id', $rombel->id);

                $totalAlokasi = (int) $alokasiRombel->sum('jam_per_minggu');
                $totalTerjadwal = 0;
                foreach ($jadwalRombel as $j) {
                    $totalTerjadwal += max(0, (int) $j->jam_selesai_ke - (int) $j->jam_mulai_ke);
                }

                $detailMapel = [];
                foreach ($alokasiRombel as $alokasi) {
                    $mapelId = $alokasi->mata_pelajaran_id;
                    $terjadwalMapel = 0;
                    foreach ($jadwalRombel->where('mata_pelajaran_id', $mapelId) as $j) {
                        $terjadwalMapel += max(0, (int) $j->jam_selesai_ke - (int) $j->jam_mulai_ke);
                    }
                    $status = 'tepat';
                    if ($terjadwalMapel < $alokasi->jam_per_minggu) {
                        $status = 'kurang';
                    } elseif ($terjadwalMapel > $alokasi->jam_per_minggu) {
                        $status = 'lebih';
                    }

                    $detailMapel[] = [
                        'mata_pelajaran_id' => $mapelId,
                        'nama_mapel' => $alokasi->mataPelajaran->nama,
                        'alokasi' => $alokasi->jam_per_minggu,
                        'terjadwal' => $terjadwalMapel,
                        'status' => $status,
                    ];
                }

                $ringkasan[] = [
                    'rombel_id' => $rombel->id,
                    'nama_rombel' => $rombel->nama,
                    'tingkat' => $rombel->tingkat,
                    'total_alokasi' => $totalAlokasi,
                    'total_terjadwal' => $totalTerjadwal,
                    'persentase' => $totalAlokasi > 0 ? min(100, (int) round(($totalTerjadwal / $totalAlokasi) * 100)) : 100,
                    'is_lengkap' => $totalAlokasi > 0 && $totalTerjadwal === $totalAlokasi,
                    'detail_mapel' => $detailMapel,
                ];
            }
        }

        $gurus = Pegawai::where('sekolah_id', $sekolahId)
            ->orderBy('nama')
            ->get();

        $ruangs = Ruang::where('sekolah_id', $sekolahId)
            ->where('is_aktif', true)
            ->orderBy('nama')
            ->get();

        $mapels = MataPelajaran::where('sekolah_id', $sekolahId)
            ->where('is_aktif', true)
            ->orderBy('nama')
            ->get();

        $jamKerja = JamKerja::where('sekolah_id', $sekolahId)
            ->where('kelompok', 'umum')
            ->orderBy('hari')
            ->get();

        return Inertia::render('jadwal/Index', [
            'semesters' => $semesters,
            'selectedSemester' => $selectedSemester,
            'rombels' => $rombels,
            'gurus' => $gurus,
            'ruangs' => $ruangs,
            'mapels' => $mapels,
            'jamKerja' => $jamKerja,
            'alokasiList' => $alokasiList,
            'jadwals' => $jadwals,
            'ringkasan' => $ringkasan,
            'canManage' => $request->user()?->can('create', JadwalPelajaran::class) ?? false,
        ]);
    }

    public function store(StoreJadwalRequest $request, ConflictDetector $detector): RedirectResponse
    {
        $sekolahId = $this->resolveSekolahId();
        $validated = $request->validated();
        $validated['sekolah_id'] = $sekolahId;

        // Deteksi seluruh kendala keras (H1 s/d H6)
        $conflict = $detector->check($validated);
        if ($conflict->hasConflict()) {
            return back()->withErrors(['conflict' => $conflict->firstMessage()])
                ->with('error', $conflict->firstMessage());
        }

        JadwalPelajaran::create($validated);

        return back()->with('success', 'Jadwal pelajaran berhasil disimpan.');
    }

    public function update(UpdateJadwalRequest $request, JadwalPelajaran $jadwal, ConflictDetector $detector): RedirectResponse
    {
        $sekolahId = $this->resolveSekolahId();
        if ($jadwal->sekolah_id !== $sekolahId) {
            abort(404);
        }

        $validated = $request->validated();
        $validated['sekolah_id'] = $sekolahId;

        // Deteksi kendala keras mengabaikan ID record saat ini
        $conflict = $detector->check($validated, ignoreId: $jadwal->id);
        if ($conflict->hasConflict()) {
            return back()->withErrors(['conflict' => $conflict->firstMessage()])
                ->with('error', $conflict->firstMessage());
        }

        $jadwal->update($validated);

        return back()->with('success', 'Jadwal pelajaran berhasil diperbarui.');
    }

    public function move(MoveJadwalRequest $request, JadwalPelajaran $jadwal, ConflictDetector $detector): JsonResponse|RedirectResponse
    {
        $sekolahId = $this->resolveSekolahId();
        if ($jadwal->sekolah_id !== $sekolahId) {
            abort(404);
        }

        $validated = $request->validated();

        $candidateData = [
            'sekolah_id' => $jadwal->sekolah_id,
            'semester_id' => $jadwal->semester_id,
            'rombel_id' => $jadwal->rombel_id,
            'mata_pelajaran_id' => $jadwal->mata_pelajaran_id,
            'guru_id' => $jadwal->guru_id,
            'ruang_id' => array_key_exists('ruang_id', $validated) ? $validated['ruang_id'] : $jadwal->ruang_id,
            'hari' => $validated['hari'],
            'jam_mulai_ke' => $validated['jam_mulai_ke'],
            'jam_selesai_ke' => $validated['jam_selesai_ke'],
        ];

        $conflict = $detector->check($candidateData, ignoreId: $jadwal->id);
        if ($conflict->hasConflict()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $conflict->firstMessage(),
                    'conflicts' => $conflict->getMessages(),
                    'conflict_type' => $conflict->conflicts[0]->type ?? null,
                ], 422);
            }

            return back()->withErrors(['conflict' => $conflict->firstMessage()])
                ->with('error', $conflict->firstMessage());
        }

        $jadwal->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Slot jadwal berhasil dipindahkan.',
                'jadwal' => $jadwal->fresh(['guru', 'rombel', 'ruang', 'mataPelajaran']),
            ]);
        }

        return back()->with('success', 'Slot jadwal berhasil dipindahkan.');
    }

    public function destroy(Request $request, JadwalPelajaran $jadwal): RedirectResponse
    {
        $sekolahId = $this->resolveSekolahId();
        if ($jadwal->sekolah_id !== $sekolahId) {
            abort(404);
        }

        Gate::authorize('delete', $jadwal);

        $jadwal->delete();

        return back()->with('success', 'Jadwal pelajaran berhasil dihapus.');
    }
}
