<?php

namespace App\Http\Controllers;

use App\Models\Ruang;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RuangController extends Controller
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

        $query = Ruang::where('sekolah_id', $sekolahId);

        if ($request->filled('kategori')) {
            $query->where('kategori', $request->query('kategori'));
        }

        $ruang = $query->orderBy('kode')->get();

        return Inertia::render('data-induk/ruang/Index', [
            'ruang' => $ruang,
            'kategoriOptions' => ['kelas', 'laboratorium', 'bengkel', 'lapangan', 'perpustakaan', 'lainnya'],
            'filters' => [
                'kategori' => $request->query('kategori', ''),
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
                Rule::unique('ruang', 'kode')->where('sekolah_id', $sekolahId),
            ],
            'nama' => ['required', 'string', 'max:100'],
            'kategori' => ['required', Rule::in(['kelas', 'laboratorium', 'bengkel', 'lapangan', 'perpustakaan', 'lainnya'])],
            'kapasitas' => ['required', 'integer', 'min:1', 'max:500'],
            'lokasi' => ['nullable', 'string', 'max:100'],
            'is_aktif' => ['nullable', 'boolean'],
        ]);

        Ruang::create([
            'sekolah_id' => $sekolahId,
            'kode' => strtoupper($validated['kode']),
            'nama' => $validated['nama'],
            'kategori' => $validated['kategori'],
            'kapasitas' => (int) $validated['kapasitas'],
            'lokasi' => $validated['lokasi'] ?? null,
            'is_aktif' => (bool) ($validated['is_aktif'] ?? true),
        ]);

        return back()->with('success', 'Ruangan berhasil ditambahkan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        abort_unless(auth()->user()->can('data_induk.update'), 403);

        $sekolahId = $this->resolveSekolahId();
        $ruang = Ruang::where('sekolah_id', $sekolahId)->where('id', $id)->firstOrFail();

        $validated = $request->validate([
            'kode' => [
                'required',
                'string',
                'max:30',
                Rule::unique('ruang', 'kode')
                    ->where('sekolah_id', $sekolahId)
                    ->ignore($ruang->id),
            ],
            'nama' => ['required', 'string', 'max:100'],
            'kategori' => ['required', Rule::in(['kelas', 'laboratorium', 'bengkel', 'lapangan', 'perpustakaan', 'lainnya'])],
            'kapasitas' => ['required', 'integer', 'min:1', 'max:500'],
            'lokasi' => ['nullable', 'string', 'max:100'],
            'is_aktif' => ['nullable', 'boolean'],
        ]);

        $ruang->update([
            'kode' => strtoupper($validated['kode']),
            'nama' => $validated['nama'],
            'kategori' => $validated['kategori'],
            'kapasitas' => (int) $validated['kapasitas'],
            'lokasi' => $validated['lokasi'] ?? null,
            'is_aktif' => (bool) ($validated['is_aktif'] ?? true),
        ]);

        return back()->with('success', 'Ruangan berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        abort_unless(auth()->user()->can('data_induk.delete'), 403);

        $sekolahId = $this->resolveSekolahId();
        $ruang = Ruang::where('sekolah_id', $sekolahId)->where('id', $id)->firstOrFail();

        $ruang->delete();

        return back()->with('success', 'Ruangan berhasil dihapus.');
    }
}
