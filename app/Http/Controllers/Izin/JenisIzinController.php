<?php

namespace App\Http\Controllers\Izin;

use App\Http\Controllers\Controller;
use App\Models\JenisIzin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * JenisIzinController — CRUD konfigurasi jenis izin (T-09.01).
 *
 * Hanya Operator dan Super Admin yang bisa mengelola.
 * Jenis izin global (sekolah_id = null) hanya bisa dilihat, tidak diedit.
 */
class JenisIzinController extends Controller
{
    public function index(): Response
    {
        abort_unless(auth()->user()?->hasRole(['operator', 'super_admin']), 403);

        $sekolahId = session('sekolah_id');

        // Tampilkan: jenis global (sekolah_id=null) + kustom sekolah ini
        $jenisIzin = JenisIzin::where(function ($q) use ($sekolahId) {
            $q->whereNull('sekolah_id')
                ->orWhere('sekolah_id', $sekolahId);
        })
            ->orderByRaw('sekolah_id IS NOT NULL, nama')
            ->get(['id', 'sekolah_id', 'nama', 'kode', 'butuh_lampiran', 'butuh_persetujuan', 'mengurangi_kuota_cuti', 'urutan_approval', 'is_aktif']);

        return Inertia::render('izin/JenisIzinIndex', [
            'jenisIzin' => $jenisIzin,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->hasRole(['operator', 'super_admin']), 403);

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'kode' => ['nullable', 'string', 'max:50', 'alpha_dash'],
            'butuh_lampiran' => ['boolean'],
            'butuh_persetujuan' => ['boolean'],
            'mengurangi_kuota_cuti' => ['boolean'],
            'urutan_approval' => ['array'],
            'urutan_approval.*' => ['string'],
        ]);

        $sekolahId = session('sekolah_id');

        // Cek nama unik per sekolah
        $sudahAda = JenisIzin::where('sekolah_id', $sekolahId)
            ->where('nama', $data['nama'])
            ->exists();

        if ($sudahAda) {
            return back()->withErrors(['nama' => 'Jenis izin dengan nama ini sudah ada.']);
        }

        JenisIzin::create(array_merge($data, ['sekolah_id' => $sekolahId, 'is_aktif' => true]));

        return back()->with('success', 'Jenis izin berhasil ditambahkan.');
    }

    public function update(Request $request, JenisIzin $jenisIzin): RedirectResponse
    {
        abort_unless(auth()->user()?->hasRole(['operator', 'super_admin']), 403);

        // Tidak boleh edit jenis global
        if ($jenisIzin->sekolah_id === null) {
            abort(403, 'Jenis izin default tidak dapat diubah.');
        }

        abort_unless($jenisIzin->sekolah_id === session('sekolah_id'), 404);

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'kode' => ['nullable', 'string', 'max:50', 'alpha_dash'],
            'butuh_lampiran' => ['boolean'],
            'butuh_persetujuan' => ['boolean'],
            'mengurangi_kuota_cuti' => ['boolean'],
            'urutan_approval' => ['array'],
            'urutan_approval.*' => ['string'],
            'is_aktif' => ['boolean'],
        ]);

        $jenisIzin->update($data);

        return back()->with('success', 'Jenis izin berhasil diperbarui.');
    }

    public function destroy(JenisIzin $jenisIzin): RedirectResponse
    {
        abort_unless(auth()->user()?->hasRole(['operator', 'super_admin']), 403);

        if ($jenisIzin->sekolah_id === null) {
            abort(403, 'Jenis izin default tidak dapat dihapus.');
        }

        abort_unless($jenisIzin->sekolah_id === session('sekolah_id'), 404);

        if ($jenisIzin->pengajuan()->exists()) {
            return back()->withErrors(['jenis_izin' => 'Tidak dapat menghapus jenis izin yang sudah dipakai.']);
        }

        $jenisIzin->delete();

        return back()->with('success', 'Jenis izin berhasil dihapus.');
    }
}
