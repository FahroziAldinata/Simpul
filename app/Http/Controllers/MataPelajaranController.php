<?php

namespace App\Http\Controllers;

use App\Models\Jurusan;
use App\Models\MataPelajaran;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MataPelajaranController extends Controller
{
    private function resolveSekolahId(): string
    {
        $sekolahId = session('sekolah_id') ?? auth()->user()->sekolah_id;
        if (! $sekolahId) {
            abort(404, 'Sekolah tidak ditemukan.');
        }

        return $sekolahId;
    }

    public function index(Request $request): Response
    {
        abort_unless(auth()->user()->can('data_induk.view'), 403);

        $sekolahId = $this->resolveSekolahId();

        $query = MataPelajaran::where('sekolah_id', $sekolahId)->with('jurusan');

        if ($request->filled('kelompok')) {
            $query->where('kelompok', $request->query('kelompok'));
        }

        if ($request->filled('bobot')) {
            $query->where('bobot_beban_kognitif', $request->query('bobot'));
        }

        $mataPelajaran = $query->orderBy('kode')->get();
        $jurusanList = Jurusan::where('sekolah_id', $sekolahId)->where('is_aktif', true)->orderBy('nama')->get();

        return Inertia::render('data-induk/mata-pelajaran/Index', [
            'mataPelajaran' => $mataPelajaran,
            'jurusanList' => $jurusanList,
            'kelompokOptions' => ['umum_a', 'umum_b', 'peminatan', 'kejuruan', 'muatan_lokal', 'lainnya'],
            'bobotOptions' => ['ringan', 'sedang', 'berat'],
            'ruangKategoriOptions' => ['kelas', 'laboratorium', 'bengkel', 'lapangan', 'perpustakaan', 'lainnya'],
            'filters' => [
                'kelompok' => $request->query('kelompok', ''),
                'bobot' => $request->query('bobot', ''),
            ],
            'canManage' => (bool) auth()->user()->can('data_induk.create'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('data_induk.create'), 403);

        $sekolahId = $this->resolveSekolahId();

        $validated = $request->validate([
            'kode' => [
                'required',
                'string',
                'max:30',
                Rule::unique('mata_pelajaran', 'kode')->where('sekolah_id', $sekolahId),
            ],
            'nama' => ['required', 'string', 'max:100'],
            'kelompok' => ['required', Rule::in(['umum_a', 'umum_b', 'peminatan', 'kejuruan', 'muatan_lokal', 'lainnya'])],
            'tingkat' => ['nullable', 'integer', 'min:1', 'max:12'],
            'jurusan_id' => [
                'nullable',
                Rule::exists('jurusan', 'id')->where('sekolah_id', $sekolahId),
            ],
            'bobot_beban_kognitif' => ['required', Rule::in(['ringan', 'sedang', 'berat'])],
            'butuh_ruang_kategori' => ['nullable', Rule::in(['kelas', 'laboratorium', 'bengkel', 'lapangan', 'perpustakaan', 'lainnya'])],
            'is_aktif' => ['nullable', 'boolean'],
        ]);

        MataPelajaran::create([
            'sekolah_id' => $sekolahId,
            'kode' => strtoupper($validated['kode']),
            'nama' => $validated['nama'],
            'kelompok' => $validated['kelompok'],
            'tingkat' => $validated['tingkat'] ? (int) $validated['tingkat'] : null,
            'jurusan_id' => $validated['jurusan_id'] ?? null,
            'bobot_beban_kognitif' => $validated['bobot_beban_kognitif'],
            'butuh_ruang_kategori' => $validated['butuh_ruang_kategori'] ?? null,
            'is_aktif' => (bool) ($validated['is_aktif'] ?? true),
        ]);

        return back()->with('success', 'Mata pelajaran berhasil ditambahkan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        abort_unless(auth()->user()->can('data_induk.update'), 403);

        $sekolahId = $this->resolveSekolahId();
        $mapel = MataPelajaran::where('sekolah_id', $sekolahId)->where('id', $id)->firstOrFail();

        $validated = $request->validate([
            'kode' => [
                'required',
                'string',
                'max:30',
                Rule::unique('mata_pelajaran', 'kode')
                    ->where('sekolah_id', $sekolahId)
                    ->ignore($mapel->id),
            ],
            'nama' => ['required', 'string', 'max:100'],
            'kelompok' => ['required', Rule::in(['umum_a', 'umum_b', 'peminatan', 'kejuruan', 'muatan_lokal', 'lainnya'])],
            'tingkat' => ['nullable', 'integer', 'min:1', 'max:12'],
            'jurusan_id' => [
                'nullable',
                Rule::exists('jurusan', 'id')->where('sekolah_id', $sekolahId),
            ],
            'bobot_beban_kognitif' => ['required', Rule::in(['ringan', 'sedang', 'berat'])],
            'butuh_ruang_kategori' => ['nullable', Rule::in(['kelas', 'laboratorium', 'bengkel', 'lapangan', 'perpustakaan', 'lainnya'])],
            'is_aktif' => ['nullable', 'boolean'],
        ]);

        $mapel->update([
            'kode' => strtoupper($validated['kode']),
            'nama' => $validated['nama'],
            'kelompok' => $validated['kelompok'],
            'tingkat' => $validated['tingkat'] ? (int) $validated['tingkat'] : null,
            'jurusan_id' => $validated['jurusan_id'] ?? null,
            'bobot_beban_kognitif' => $validated['bobot_beban_kognitif'],
            'butuh_ruang_kategori' => $validated['butuh_ruang_kategori'] ?? null,
            'is_aktif' => (bool) ($validated['is_aktif'] ?? true),
        ]);

        return back()->with('success', 'Mata pelajaran berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        abort_unless(auth()->user()->can('data_induk.delete'), 403);

        $sekolahId = $this->resolveSekolahId();
        $mapel = MataPelajaran::where('sekolah_id', $sekolahId)->where('id', $id)->firstOrFail();

        $mapel->delete();

        return back()->with('success', 'Mata pelajaran berhasil dihapus.');
    }
}
