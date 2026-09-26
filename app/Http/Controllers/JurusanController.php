<?php

namespace App\Http\Controllers;

use App\Models\Jurusan;
use App\Models\Sekolah;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class JurusanController extends Controller
{
    private function resolveSekolah(): Sekolah
    {
        $sekolahId = session('sekolah_id') ?? auth()->user()->sekolah_id;
        if (! $sekolahId) {
            abort(404, 'Sekolah tidak ditemukan.');
        }

        return Sekolah::findOrFail($sekolahId);
    }

    public function index(): Response
    {
        abort_unless(auth()->user()->can('data_induk.view'), 403);

        $sekolah = $this->resolveSekolah();
        $isApplicable = in_array(strtolower($sekolah->jenjang), ['sma', 'smk'], true);

        $jurusan = $isApplicable
            ? Jurusan::where('sekolah_id', $sekolah->id)->orderBy('kode')->get()
            : [];

        return Inertia::render('data-induk/jurusan/Index', [
            'jurusan' => $jurusan,
            'isApplicable' => $isApplicable,
            'jenjang' => strtoupper($sekolah->jenjang),
            'canManage' => (bool) auth()->user()->can('data_induk.create'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('data_induk.create'), 403);

        $sekolah = $this->resolveSekolah();
        abort_unless(in_array(strtolower($sekolah->jenjang), ['sma', 'smk'], true), 422, 'Jenjang sekolah ini tidak menggunakan jurusan.');

        $validated = $request->validate([
            'kode' => [
                'required',
                'string',
                'max:20',
                Rule::unique('jurusan', 'kode')->where('sekolah_id', $sekolah->id),
            ],
            'nama' => ['required', 'string', 'max:100'],
            'bidang_keahlian' => ['nullable', 'string', 'max:100'],
            'program_keahlian' => ['nullable', 'string', 'max:100'],
            'is_aktif' => ['nullable', 'boolean'],
        ]);

        Jurusan::create([
            'sekolah_id' => $sekolah->id,
            'kode' => strtoupper($validated['kode']),
            'nama' => $validated['nama'],
            'bidang_keahlian' => $validated['bidang_keahlian'] ?? null,
            'program_keahlian' => $validated['program_keahlian'] ?? null,
            'is_aktif' => (bool) ($validated['is_aktif'] ?? true),
        ]);

        return back()->with('success', 'Jurusan berhasil ditambahkan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        abort_unless(auth()->user()->can('data_induk.update'), 403);

        $sekolah = $this->resolveSekolah();
        $jurusan = Jurusan::where('sekolah_id', $sekolah->id)->where('id', $id)->firstOrFail();

        $validated = $request->validate([
            'kode' => [
                'required',
                'string',
                'max:20',
                Rule::unique('jurusan', 'kode')
                    ->where('sekolah_id', $sekolah->id)
                    ->ignore($jurusan->id),
            ],
            'nama' => ['required', 'string', 'max:100'],
            'bidang_keahlian' => ['nullable', 'string', 'max:100'],
            'program_keahlian' => ['nullable', 'string', 'max:100'],
            'is_aktif' => ['nullable', 'boolean'],
        ]);

        $jurusan->update([
            'kode' => strtoupper($validated['kode']),
            'nama' => $validated['nama'],
            'bidang_keahlian' => $validated['bidang_keahlian'] ?? null,
            'program_keahlian' => $validated['program_keahlian'] ?? null,
            'is_aktif' => (bool) ($validated['is_aktif'] ?? true),
        ]);

        return back()->with('success', 'Jurusan berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        abort_unless(auth()->user()->can('data_induk.delete'), 403);

        $sekolah = $this->resolveSekolah();
        $jurusan = Jurusan::where('sekolah_id', $sekolah->id)->where('id', $id)->firstOrFail();

        $jurusan->delete();

        return back()->with('success', 'Jurusan berhasil dihapus.');
    }
}
