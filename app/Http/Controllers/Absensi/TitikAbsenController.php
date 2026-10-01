<?php

namespace App\Http\Controllers\Absensi;

use App\Http\Controllers\Controller;
use App\Models\TitikAbsen;
use App\Services\QrTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * TitikAbsenController — CRUD titik check-in QR (T-08.03).
 *
 * Akses: Operator dan Super Admin (Keputusan Minggu 8 #2 & #3).
 */
class TitikAbsenController extends Controller
{
    public function __construct(private readonly QrTokenService $qrTokenService) {}

    /**
     * Daftar titik absen milik sekolah aktif.
     */
    public function index(): Response
    {
        abort_unless(
            auth()->user()?->hasRole(['operator', 'super_admin']),
            403
        );

        $titikAbsen = TitikAbsen::orderBy('nama')->get()->map(fn (TitikAbsen $t) => [
            'id' => $t->id,
            'nama' => $t->nama,
            'latitude' => $t->latitude,
            'longitude' => $t->longitude,
            'is_aktif' => $t->is_aktif,
            // secret TIDAK dikembalikan ke frontend
        ]);

        return Inertia::render('absensi/TitikAbsenIndex', [
            'titikAbsen' => $titikAbsen,
        ]);
    }

    /**
     * Simpan titik absen baru. Secret di-generate otomatis (aman, random 64 char).
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless(
            auth()->user()?->hasRole(['operator', 'super_admin']),
            403
        );

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_aktif' => ['boolean'],
        ]);

        // Secret di-generate server-side — bukan input dari user
        $data['secret'] = bin2hex(random_bytes(32)); // 64 karakter hex

        TitikAbsen::create($data);

        return redirect()->route('absensi.titik.index')
            ->with('success', 'Titik absen berhasil ditambahkan.');
    }

    /**
     * Update nama, koordinat, dan status aktif. Secret tidak bisa diubah via form biasa.
     */
    public function update(Request $request, TitikAbsen $titikAbsen): RedirectResponse
    {
        abort_unless(
            auth()->user()?->hasRole(['operator', 'super_admin']),
            403
        );

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_aktif' => ['boolean'],
        ]);

        $titikAbsen->update($data);

        return redirect()->route('absensi.titik.index')
            ->with('success', 'Titik absen berhasil diperbarui.');
    }

    /**
     * Rotasi secret — generate ulang secret titik absen ini.
     * Akibat: semua QR lama yang sudah di-screenshot langsung tidak valid.
     */
    public function rotasiSecret(TitikAbsen $titikAbsen): RedirectResponse
    {
        abort_unless(
            auth()->user()?->hasRole(['operator', 'super_admin']),
            403
        );

        $titikAbsen->update([
            'secret' => bin2hex(random_bytes(32)),
        ]);

        return redirect()->route('absensi.titik.index')
            ->with('success', 'Secret titik absen berhasil dirotasi. QR lama langsung tidak valid.');
    }

    /**
     * Hapus titik absen (soft-delete bukan prioritas karena tidak ada data historis terikat).
     */
    public function destroy(TitikAbsen $titikAbsen): RedirectResponse
    {
        abort_unless(
            auth()->user()?->hasRole(['operator', 'super_admin']),
            403
        );

        $titikAbsen->delete();

        return redirect()->route('absensi.titik.index')
            ->with('success', 'Titik absen dihapus.');
    }

    /**
     * API: Dapatkan token QR terbaru untuk titik absen tertentu.
     * Dipakai oleh halaman tampilan QR (client-side timer fetch tiap window berganti).
     *
     * Akses: hanya Operator/Super Admin (Keputusan #2 — bukan endpoint publik).
     */
    public function token(TitikAbsen $titikAbsen): JsonResponse
    {
        abort_unless(
            auth()->user()?->hasRole(['operator', 'super_admin']),
            403
        );

        $payload = $this->qrTokenService->buatPayloadQr($titikAbsen);
        $window = $this->qrTokenService->currentWindow();
        $windowSeconds = QrTokenService::WINDOW_SECONDS;

        // Sisa detik sampai window berikutnya — dipakai client untuk menghitung kapan re-fetch
        $sisaDetik = $windowSeconds - (now()->timestamp % $windowSeconds);

        return response()->json([
            'payload' => $payload,
            'sisa_detik' => $sisaDetik,
            'window_seconds' => $windowSeconds,
        ]);
    }
}
