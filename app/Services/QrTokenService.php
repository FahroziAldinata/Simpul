<?php

namespace App\Services;

use App\Models\TitikAbsen;
use Illuminate\Support\Str;

/**
 * QrTokenService — TOTP + HMAC untuk anti-spoofing QR Absensi.
 *
 * Skema (sesuai PRD 6.2):
 *   $window  = intdiv(now()->timestamp, 30);
 *   $payload = "{$sekolahId}|{$titikAbsenId}|{$window}";
 *   $token   = hash_hmac('sha256', $payload, $titikAbsen->secret);
 *
 * Poin keamanan:
 *  1. Secret PER titik_absen (bukan satu global) — kompromi satu titik tidak membuka yang lain.
 *  2. Perbandingan token memakai hash_equals() — mencegah timing attack.
 *  3. Toleransi window ±1 (30 detik clock drift / waktu pemindaian).
 *  4. Window > ±1 (>60 detik) DITOLAK — screenshot lama tidak bisa dipakai.
 */
class QrTokenService
{
    /**
     * Panjang window dalam detik (30 detik, sesuai PRD 6.2).
     */
    public const WINDOW_SECONDS = 30;

    /**
     * Buat payload QR untuk ditampilkan di layar kantor.
     *
     * Format output (base64-encoded): "{sekolahId}.{titikAbsenId}.{window}.{token}"
     */
    public function buatPayloadQr(TitikAbsen $titikAbsen): string
    {
        $window = $this->currentWindow();
        $token = $this->computeToken($titikAbsen->sekolah_id, $titikAbsen->id, $window, $titikAbsen->secret);

        $raw = implode('.', [
            $titikAbsen->sekolah_id,
            $titikAbsen->id,
            $window,
            $token,
        ]);

        return base64_encode($raw);
    }

    /**
     * Validasi payload QR yang discan oleh perangkat guru.
     *
     * @return array{valid: bool, sekolah_id: string|null, titik_absen_id: string|null, alasan: string|null}
     */
    public function validasiPayload(string $payloadBase64): array
    {
        $invalid = fn (string $alasan) => [
            'valid' => false,
            'sekolah_id' => null,
            'titik_absen_id' => null,
            'alasan' => $alasan,
        ];

        // Decode base64
        $raw = base64_decode($payloadBase64, strict: true);
        if ($raw === false) {
            return $invalid('Format QR tidak valid.');
        }

        // Pisah komponen
        $parts = explode('.', $raw, 4);
        if (count($parts) !== 4) {
            return $invalid('Format QR tidak valid.');
        }

        [$sekolahId, $titikAbsenId, $claimedWindowStr, $claimedToken] = $parts;

        if (! Str::isUuid($sekolahId) || ! Str::isUuid($titikAbsenId)) {
            return $invalid('Format QR tidak valid.');
        }

        if (! ctype_digit($claimedWindowStr)) {
            return $invalid('Format QR tidak valid.');
        }

        $claimedWindow = (int) $claimedWindowStr;

        // Cek apakah window masih dalam toleransi ±1
        $currentWindow = $this->currentWindow();
        if (abs($currentWindow - $claimedWindow) > 1) {
            return $invalid('QR sudah kedaluwarsa. Minta QR terbaru.');
        }

        // Ambil titik absen dan secret-nya
        $titikAbsen = TitikAbsen::where('id', $titikAbsenId)
            ->where('sekolah_id', $sekolahId)
            ->where('is_aktif', true)
            ->first();

        if (! $titikAbsen) {
            return $invalid('Titik absen tidak dikenali atau tidak aktif.');
        }

        // Hitung token yang seharusnya untuk window yang diklaim
        $expectedToken = $this->computeToken($sekolahId, $titikAbsenId, $claimedWindow, $titikAbsen->secret);

        // WAJIB hash_equals() — mencegah timing attack (dibandingkan === tidak aman)
        if (! hash_equals($expectedToken, $claimedToken)) {
            return $invalid('Token QR tidak valid.');
        }

        return [
            'valid' => true,
            'sekolah_id' => $sekolahId,
            'titik_absen_id' => $titikAbsenId,
            'alasan' => null,
        ];
    }

    /**
     * Hitung HMAC-SHA256 token untuk satu window.
     *
     * @internal Gunakan hanya lewat buatPayloadQr() dan validasiPayload()
     */
    public function computeToken(string $sekolahId, string $titikAbsenId, int $window, string $secret): string
    {
        $payload = "{$sekolahId}|{$titikAbsenId}|{$window}";

        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Window saat ini: floor(Unix timestamp / 30).
     */
    public function currentWindow(): int
    {
        return intdiv(now()->timestamp, self::WINDOW_SECONDS);
    }
}
