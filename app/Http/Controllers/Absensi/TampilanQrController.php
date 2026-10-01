<?php

namespace App\Http\Controllers\Absensi;

use App\Http\Controllers\Controller;
use App\Models\TitikAbsen;
use App\Services\QrTokenService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * TampilanQrController — Halaman layar kantor QR (T-08.04).
 *
 * Akses dibatasi hanya Operator dan Super Admin (Keputusan Minggu 8 #2).
 * Guru tidak bisa membuka halaman ini — mereka hanya bisa membuka halaman scan.
 *
 * Mekanisme refresh: client-side timer (Keputusan #1).
 * Client JS menghitung sisa waktu window, fetch token baru tepat saat window berganti.
 * Tidak perlu Reverb — window sudah deterministik berbasis timestamp.
 */
class TampilanQrController extends Controller
{
    public function __construct(private readonly QrTokenService $qrTokenService) {}

    /**
     * Halaman tampilan QR (layar kantor) — pilih titik absen aktif.
     *
     * Jika sekolah hanya punya satu titik aktif, langsung arahkan ke tampilan QR titik tersebut.
     */
    public function index(): Response|RedirectResponse
    {
        abort_unless(
            auth()->user()?->hasRole(['operator', 'super_admin']),
            403
        );

        $titikAktif = TitikAbsen::where('is_aktif', true)
            ->orderBy('nama')
            ->get(['id', 'nama']);

        if ($titikAktif->count() === 1) {
            return redirect()->route('absensi.qr.tampil', $titikAktif->first()->id);
        }

        return Inertia::render('absensi/PilihTitikQr', [
            'titikAktif' => $titikAktif,
        ]);
    }

    /**
     * Halaman tampilan QR untuk titik absen tertentu.
     *
     * Mengirim token pertama via Inertia props, refresh selanjutnya via fetch ke endpoint token().
     */
    public function tampil(TitikAbsen $titikAbsen): Response
    {
        abort_unless(
            auth()->user()?->hasRole(['operator', 'super_admin']),
            403
        );

        abort_unless($titikAbsen->is_aktif, 404);

        $payload = $this->qrTokenService->buatPayloadQr($titikAbsen);
        $windowSeconds = QrTokenService::WINDOW_SECONDS;
        $sisaDetik = $windowSeconds - (now()->timestamp % $windowSeconds);

        return Inertia::render('absensi/TampilanQr', [
            'titikAbsen' => [
                'id' => $titikAbsen->id,
                'nama' => $titikAbsen->nama,
                // secret TIDAK disertakan
            ],
            'payload' => $payload,
            'sisaDetik' => $sisaDetik,
            'windowSeconds' => $windowSeconds,
            // URL endpoint fetch token — dipakai client timer
            'tokenUrl' => route('absensi.titik.token', $titikAbsen->id),
        ]);
    }
}
