<?php

namespace App\Http\Controllers\Absensi;

use App\Http\Controllers\Controller;
use App\Models\JamKerja;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * JamKerjaController — CRUD jam kerja per kelompok pegawai (T-08.02).
 *
 * Extend dari model JamKerja yang dibuat Minggu 4 untuk jam operasional sekolah.
 * Minggu 8 menambahkan kelompok spesifik (guru, tu, dll) dan toleransi_menit.
 */
class JamKerjaController extends Controller
{
    /**
     * Daftar semua jadwal jam kerja sekolah aktif, dikelompokkan per kelompok.
     */
    public function index(): Response
    {
        abort_unless(
            auth()->user()?->hasRole(['operator', 'super_admin']),
            403
        );

        $jamKerja = JamKerja::orderBy('kelompok')->orderBy('hari')->get();

        $kelompokList = $jamKerja->groupBy('kelompok');

        return Inertia::render('absensi/JamKerjaIndex', [
            'jamKerja' => $jamKerja,
            'kelompokList' => $kelompokList->keys()->values(),
        ]);
    }

    /**
     * Simpan atau update jadwal jam kerja untuk satu hari satu kelompok.
     * Menggunakan upsert berdasarkan (sekolah_id, kelompok, hari).
     */
    public function upsert(Request $request): RedirectResponse
    {
        abort_unless(
            auth()->user()?->hasRole(['operator', 'super_admin']),
            403
        );

        $data = $request->validate([
            'kelompok' => ['required', 'string', 'max:20'],
            'hari' => ['required', 'integer', 'between:1,7'],
            'jam_masuk' => ['nullable', 'date_format:H:i'],
            'jam_pulang' => ['nullable', 'date_format:H:i', 'after:jam_masuk'],
            'toleransi_menit' => ['required', 'integer', 'min:0', 'max:120'],
            'is_libur' => ['boolean'],
            'jumlah_jam_pelajaran' => ['required', 'integer', 'min:0', 'max:16'],
        ]);

        JamKerja::updateOrCreate(
            [
                'sekolah_id' => session('sekolah_id'),
                'kelompok' => $data['kelompok'],
                'hari' => $data['hari'],
            ],
            [
                'jam_masuk' => $data['jam_masuk'],
                'jam_pulang' => $data['jam_pulang'],
                'toleransi_menit' => $data['toleransi_menit'],
                'is_libur' => $data['is_libur'] ?? false,
                'jumlah_jam_pelajaran' => $data['jumlah_jam_pelajaran'],
            ]
        );

        return back()->with('success', 'Jam kerja berhasil disimpan.');
    }

    /**
     * Hapus jadwal jam kerja (satu baris hari tertentu dari satu kelompok).
     */
    public function destroy(JamKerja $jamKerja): RedirectResponse
    {
        abort_unless(
            auth()->user()?->hasRole(['operator', 'super_admin']),
            403
        );

        $jamKerja->delete();

        return back()->with('success', 'Jadwal jam kerja dihapus.');
    }
}
