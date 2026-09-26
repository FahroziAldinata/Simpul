<?php

namespace App\Http\Controllers;

use App\Models\AlokasiJamMapel;
use App\Models\JamKerja;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Rombel;
use App\Models\Semester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AlokasiJamMapelController extends Controller
{
    private function resolveSekolahId(): string
    {
        $sekolahId = session('sekolah_id') ?? auth()->user()->sekolah_id;
        if (! $sekolahId) {
            abort(404, 'Sekolah tidak ditemukan.');
        }

        return $sekolahId;
    }

    private function resolveCurrentSemester(string $sekolahId): ?Semester
    {
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
        abort_unless(auth()->user()->can('data_induk.view'), 403);

        $sekolahId = $this->resolveSekolahId();
        $semester = $this->resolveCurrentSemester($sekolahId);

        $rombelList = [];
        $selectedRombel = null;
        $alokasiList = [];
        $totalAlokasi = 0;
        $totalSlot = JamKerja::totalSlotMingguan($sekolahId);

        if ($semester) {
            $rombelList = Rombel::where('sekolah_id', $sekolahId)
                ->where('semester_id', $semester->id)
                ->orderBy('tingkat')
                ->orderBy('nama')
                ->get();

            $selectedRombelId = $request->query('rombel_id', $rombelList->first()?->id);

            if ($selectedRombelId) {
                $selectedRombel = $rombelList->firstWhere('id', $selectedRombelId);
                if ($selectedRombel) {
                    $alokasiList = AlokasiJamMapel::where('rombel_id', $selectedRombel->id)
                        ->with(['mataPelajaran', 'guru'])
                        ->get();

                    $totalAlokasi = (int) $alokasiList->sum('jam_per_minggu');
                }
            }
        }

        $mapelList = MataPelajaran::where('sekolah_id', $sekolahId)
            ->where('is_aktif', true)
            ->orderBy('nama')
            ->get();

        $guruList = Pegawai::where('sekolah_id', $sekolahId)
            ->where('jenis', 'guru')
            ->orderBy('nama')
            ->get(['id', 'nama', 'nip', 'nuptk']);

        return Inertia::render('data-induk/alokasi-jam/Index', [
            'currentSemester' => $semester,
            'rombelList' => $rombelList,
            'selectedRombel' => $selectedRombel,
            'alokasiList' => $alokasiList,
            'totalAlokasi' => $totalAlokasi,
            'totalSlot' => $totalSlot,
            'mapelList' => $mapelList,
            'guruList' => $guruList,
            'canManage' => (bool) auth()->user()->can('data_induk.create'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('data_induk.create'), 403);

        $sekolahId = $this->resolveSekolahId();

        $validated = $request->validate([
            'rombel_id' => [
                'required',
                Rule::exists('rombel', 'id')->where('sekolah_id', $sekolahId),
            ],
            'mata_pelajaran_id' => [
                'required',
                Rule::exists('mata_pelajaran', 'id')->where('sekolah_id', $sekolahId),
            ],
            'guru_id' => [
                'nullable',
                Rule::exists('pegawai', 'id')->where('sekolah_id', $sekolahId),
            ],
            'jam_per_minggu' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $exists = AlokasiJamMapel::where('rombel_id', $validated['rombel_id'])
            ->where('mata_pelajaran_id', $validated['mata_pelajaran_id'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['mata_pelajaran_id' => 'Mata pelajaran ini sudah dialokasikan pada rombel tersebut.']);
        }

        $totalSlot = JamKerja::totalSlotMingguan($sekolahId);
        $currentTotal = (int) AlokasiJamMapel::where('rombel_id', $validated['rombel_id'])->sum('jam_per_minggu');
        $newTotal = $currentTotal + (int) $validated['jam_per_minggu'];

        if ($newTotal > $totalSlot) {
            return back()->withErrors([
                'jam_per_minggu' => "Total alokasi jam ($newTotal JP) melebihi batas jam operasional sekolah ($totalSlot JP/minggu). Sisa slot yang tersedia adalah ".($totalSlot - $currentTotal).' JP.',
            ]);
        }

        AlokasiJamMapel::create([
            'sekolah_id' => $sekolahId,
            'rombel_id' => $validated['rombel_id'],
            'mata_pelajaran_id' => $validated['mata_pelajaran_id'],
            'guru_id' => $validated['guru_id'] ?? null,
            'jam_per_minggu' => (int) $validated['jam_per_minggu'],
        ]);

        return back()->with('success', 'Alokasi jam pelajaran berhasil ditambahkan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        abort_unless(auth()->user()->can('data_induk.update'), 403);

        $sekolahId = $this->resolveSekolahId();
        $alokasi = AlokasiJamMapel::where('sekolah_id', $sekolahId)->where('id', $id)->firstOrFail();

        $validated = $request->validate([
            'guru_id' => [
                'nullable',
                Rule::exists('pegawai', 'id')->where('sekolah_id', $sekolahId),
            ],
            'jam_per_minggu' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $totalSlot = JamKerja::totalSlotMingguan($sekolahId);
        $otherTotal = (int) AlokasiJamMapel::where('rombel_id', $alokasi->rombel_id)
            ->where('id', '!=', $alokasi->id)
            ->sum('jam_per_minggu');
        $newTotal = $otherTotal + (int) $validated['jam_per_minggu'];

        if ($newTotal > $totalSlot) {
            return back()->withErrors([
                'jam_per_minggu' => "Total alokasi jam ($newTotal JP) melebihi batas jam operasional sekolah ($totalSlot JP/minggu). Sisa slot yang tersedia adalah ".($totalSlot - $otherTotal).' JP.',
            ]);
        }

        $alokasi->update([
            'guru_id' => $validated['guru_id'] ?? null,
            'jam_per_minggu' => (int) $validated['jam_per_minggu'],
        ]);

        return back()->with('success', 'Alokasi jam pelajaran berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        abort_unless(auth()->user()->can('data_induk.delete'), 403);

        $sekolahId = $this->resolveSekolahId();
        $alokasi = AlokasiJamMapel::where('sekolah_id', $sekolahId)->where('id', $id)->firstOrFail();

        $alokasi->delete();

        return back()->with('success', 'Alokasi jam pelajaran berhasil dihapus.');
    }
}
