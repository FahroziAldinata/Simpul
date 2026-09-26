<?php

namespace App\Http\Controllers;

use App\Models\Jurusan;
use App\Models\Pegawai;
use App\Models\Rombel;
use App\Models\Ruang;
use App\Models\Semester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RombelController extends Controller
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

        $rombel = [];
        if ($semester) {
            $query = Rombel::where('sekolah_id', $sekolahId)
                ->where('semester_id', $semester->id)
                ->with(['waliKelas', 'jurusan', 'ruang']);

            if ($request->filled('tingkat')) {
                $query->where('tingkat', (int) $request->query('tingkat'));
            }

            $rombel = $query->orderBy('tingkat')->orderBy('nama')->get();
        }

        $waliKelasList = Pegawai::where('sekolah_id', $sekolahId)
            ->where('jenis', 'guru')
            ->orderBy('nama')
            ->get(['id', 'nama', 'nip', 'nuptk']);

        $jurusanList = Jurusan::where('sekolah_id', $sekolahId)
            ->where('is_aktif', true)
            ->orderBy('nama')
            ->get(['id', 'kode', 'nama']);

        $ruangList = Ruang::where('sekolah_id', $sekolahId)
            ->where('is_aktif', true)
            ->orderBy('kode')
            ->get(['id', 'kode', 'nama', 'kategori']);

        return Inertia::render('data-induk/rombel/Index', [
            'rombel' => $rombel,
            'currentSemester' => $semester,
            'waliKelasList' => $waliKelasList,
            'jurusanList' => $jurusanList,
            'ruangList' => $ruangList,
            'filters' => [
                'tingkat' => $request->query('tingkat', ''),
            ],
            'canManage' => (bool) auth()->user()->can('data_induk.create'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('data_induk.create'), 403);

        $sekolahId = $this->resolveSekolahId();
        $semester = $this->resolveCurrentSemester($sekolahId);
        abort_unless($semester !== null, 422, 'Tidak ada semester yang aktif atau dipilih.');

        $validated = $request->validate([
            'nama' => [
                'required',
                'string',
                'max:50',
                Rule::unique('rombel', 'nama')->where('semester_id', $semester->id),
            ],
            'tingkat' => ['required', 'integer', 'min:1', 'max:12'],
            'kuota' => ['required', 'integer', 'min:1', 'max:100'],
            'jurusan_id' => [
                'nullable',
                Rule::exists('jurusan', 'id')->where('sekolah_id', $sekolahId),
            ],
            'wali_kelas_id' => [
                'nullable',
                Rule::exists('pegawai', 'id')->where('sekolah_id', $sekolahId),
                Rule::unique('rombel', 'wali_kelas_id')->where('semester_id', $semester->id),
            ],
            'ruang_id' => [
                'nullable',
                Rule::exists('ruang', 'id')->where('sekolah_id', $sekolahId),
            ],
            'is_aktif' => ['nullable', 'boolean'],
        ]);

        Rombel::create([
            'sekolah_id' => $sekolahId,
            'semester_id' => $semester->id,
            'nama' => $validated['nama'],
            'tingkat' => (int) $validated['tingkat'],
            'kuota' => (int) $validated['kuota'],
            'jurusan_id' => $validated['jurusan_id'] ?? null,
            'wali_kelas_id' => $validated['wali_kelas_id'] ?? null,
            'ruang_id' => $validated['ruang_id'] ?? null,
            'is_aktif' => (bool) ($validated['is_aktif'] ?? true),
        ]);

        return back()->with('success', 'Rombongan belajar berhasil ditambahkan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        abort_unless(auth()->user()->can('data_induk.update'), 403);

        $sekolahId = $this->resolveSekolahId();
        $rombel = Rombel::where('sekolah_id', $sekolahId)->where('id', $id)->firstOrFail();

        $validated = $request->validate([
            'nama' => [
                'required',
                'string',
                'max:50',
                Rule::unique('rombel', 'nama')
                    ->where('semester_id', $rombel->semester_id)
                    ->ignore($rombel->id),
            ],
            'tingkat' => ['required', 'integer', 'min:1', 'max:12'],
            'kuota' => ['required', 'integer', 'min:1', 'max:100'],
            'jurusan_id' => [
                'nullable',
                Rule::exists('jurusan', 'id')->where('sekolah_id', $sekolahId),
            ],
            'wali_kelas_id' => [
                'nullable',
                Rule::exists('pegawai', 'id')->where('sekolah_id', $sekolahId),
                Rule::unique('rombel', 'wali_kelas_id')
                    ->where('semester_id', $rombel->semester_id)
                    ->ignore($rombel->id),
            ],
            'ruang_id' => [
                'nullable',
                Rule::exists('ruang', 'id')->where('sekolah_id', $sekolahId),
            ],
            'is_aktif' => ['nullable', 'boolean'],
        ]);

        $rombel->update([
            'nama' => $validated['nama'],
            'tingkat' => (int) $validated['tingkat'],
            'kuota' => (int) $validated['kuota'],
            'jurusan_id' => $validated['jurusan_id'] ?? null,
            'wali_kelas_id' => $validated['wali_kelas_id'] ?? null,
            'ruang_id' => $validated['ruang_id'] ?? null,
            'is_aktif' => (bool) ($validated['is_aktif'] ?? true),
        ]);

        return back()->with('success', 'Rombongan belajar berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        abort_unless(auth()->user()->can('data_induk.delete'), 403);

        $sekolahId = $this->resolveSekolahId();
        $rombel = Rombel::where('sekolah_id', $sekolahId)->where('id', $id)->firstOrFail();

        $rombel->delete();

        return back()->with('success', 'Rombongan belajar berhasil dihapus.');
    }
}
