<?php

namespace App\Services\Kartu;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;
use RuntimeException;

class QrCodeService
{
    /**
     * Generate encrypted payload for QR Code.
     * Compact schema optimized for high readability on small physical card dimensions (~20mm x 20mm):
     * - v: schema version
     * - t: 's' for siswa, 'p' for pegawai
     * - id: UUID of siswa or pegawai
     * - sid: sekolah_id
     */
    public function generateEncryptedPayload(string $type, string $id, string $sekolahId): string
    {
        $shortType = match ($type) {
            'siswa' => 's',
            'pegawai' => 'p',
            default => throw new InvalidArgumentException("Tipe kartu tidak valid: {$type}"),
        };

        $payload = [
            'v' => 1,
            't' => $shortType,
            'id' => $id,
            'sid' => $sekolahId,
        ];

        return Crypt::encryptString((string) json_encode($payload));
    }

    /**
     * Decrypt and validate payload from scanned QR Code.
     *
     * @return array{v: int, t: string, id: string, sid: string}
     */
    public function decryptPayload(string $encryptedPayload): array
    {
        try {
            $json = Crypt::decryptString($encryptedPayload);
            $data = json_decode($json, true);

            if (! is_array($data) || ! isset($data['id'], $data['sid'], $data['t']) || ! is_string($data['id']) || ! is_string($data['sid']) || ! is_string($data['t'])) {
                throw new InvalidArgumentException('Payload QR Code tidak memiliki struktur yang valid.');
            }

            return [
                'v' => (int) ($data['v'] ?? 1),
                't' => $data['t'],
                'id' => $data['id'],
                'sid' => $data['sid'],
            ];
        } catch (DecryptException $e) {
            throw new RuntimeException('Gagal mendekripsi payload QR Code: Kunci enkripsi tidak cocok atau data rusak.', 0, $e);
        }
    }

    /**
     * Generate inline SVG string of QR code for high-resolution vector printing.
     * Uses margin 1 to maximize module size for small card dimensions.
     */
    public function generateQrSvg(string $encryptedPayload, int $size = 120): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 1),
            new SvgImageBackEnd
        );

        $writer = new Writer($renderer);

        $svg = $writer->writeString($encryptedPayload);

        // Strip XML declaration to allow clean inline embedding inside HTML
        return preg_replace('/<\?xml.*?\?>/i', '', $svg) ?? $svg;
    }
}
