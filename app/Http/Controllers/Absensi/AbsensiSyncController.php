<?php

namespace App\Http\Controllers\Absensi;

use App\Enums\JenisAbsensi;
use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Services\AbsensiService;
use App\Services\IdempotencyStore;
use App\Services\QrTokenService;
use App\Services\StatusKehadiranCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * AbsensiSyncController — endpoint sinkronisasi batch antrean absensi offline (T-10.05).
 *
 * Menerima array item antrean yang dikumpulkan PWA saat offline.
 * Dilindungi oleh auth sesi web + Idempotency-Key (Keputusan #4).
 * Dikecualikan dari CSRF middleware agar Service Worker dapat memicu sync tanpa akses ke DOM token.
 */
class AbsensiSyncController extends Controller
{
    public function __construct(
        private readonly QrTokenService $qrTokenService,
        private readonly AbsensiService $absensiService,
        private readonly StatusKehadiranCalculator $statusCalculator,
        private readonly IdempotencyStore $idempotencyStore,
    ) {}

    /**
     * POST /api/absensi/sync
     */
    public function sync(Request $request): JsonResponse
    {
        $user = $request->user();
        $pegawai = $user?->pegawai;

        if (! $pegawai) {
            return response()->json([
                'sukses' => false,
                'pesan' => 'Hanya pegawai yang dapat menyinkronkan data absensi.',
            ], 403);
        }

        // Keputusan #1: sekolah_id diambil dari profil pegawai yang login, bukan dari session
        $sekolahId = $pegawai->sekolah_id;

        // Cek header Idempotency-Key jika ada (T-10.06 & ADR-007)
        $idempotencyKey = $request->header('Idempotency-Key');
        if ($idempotencyKey) {
            $cached = $this->idempotencyStore->find($idempotencyKey, $sekolahId);
            if ($cached !== null) {
                return response()->json($cached['response_body'], $cached['status_code']);
            }
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.client_uuid' => ['required', 'string'],
            'items.*.token_qr' => ['required', 'string'],
            'items.*.jenis' => ['required', 'in:masuk,pulang'],
            'items.*.captured_at' => ['required', 'numeric'],
            'items.*.clock_offset' => ['nullable', 'numeric'],
            'items.*.lat' => ['nullable', 'numeric'],
            'items.*.lng' => ['nullable', 'numeric'],
        ]);

        $waktuTerima = now();
        $hasil = [];

        /** @var array<string, mixed> $item */
        foreach ($validated['items'] as $item) {
            $clientUuid = (string) $item['client_uuid'];

            // 1. Cek apakah client_uuid ini sudah tersimpan di database (idempoten tingkat item)
            $existing = Absensi::where('client_uuid', $clientUuid)->first();
            if ($existing) {
                $hasil[] = [
                    'client_uuid' => $clientUuid,
                    'status' => 'synced',
                    'id' => $existing->id,
                    'pesan' => 'Tersinkron (sudah ada).',
                ];

                continue;
            }

            // 2. Koreksi waktu dan deteksi clock drift (T-10.09 & Keputusan #3)
            $rawCaptured = Carbon::createFromTimestampMs((int) $item['captured_at']);
            $offsetMs = (int) ($item['clock_offset'] ?? 0);
            $waktuKoreksi = Carbon::createFromTimestampMs((int) $item['captured_at'] + $offsetMs);

            $selisihDetik = abs($waktuTerima->timestamp - $rawCaptured->timestamp);
            $perluDitinjau = false;

            // Clock drift > 12 jam atau captured_at di masa depan
            if ($selisihDetik > 12 * 3600 || $rawCaptured->timestamp > ($waktuTerima->timestamp + 60)) {
                $perluDitinjau = true;
            }

            $tanggal = $waktuKoreksi->toDateString();
            $jenis = JenisAbsensi::from((string) $item['jenis']);

            // 3. Guard izin disetujui (Minggu 9 & Keputusan #2)
            // Pegawai yang sudah memiliki izin/cuti disetujui pada tanggal ini tidak boleh absen
            $adaIzinDisetujui = Absensi::where('pegawai_id', $pegawai->id)
                ->where('tanggal', $tanggal)
                ->where('jenis', 'masuk')
                ->where('sumber', 'izin')
                ->whereIn('status', ['izin', 'sakit', 'cuti', 'dinas'])
                ->exists();

            if ($adaIzinDisetujui) {
                $hasil[] = [
                    'client_uuid' => $clientUuid,
                    'status' => 'conflict_final',
                    'kode' => 'izin_disetujui',
                    'pesan' => 'Dibatalkan: ada izin disetujui untuk tanggal ini.',
                ];

                continue;
            }

            // 4. Cek duplikasi absensi pada hari dan jenis yang sama
            $sudahAbsenHariIni = Absensi::where('pegawai_id', $pegawai->id)
                ->where('tanggal', $tanggal)
                ->where('jenis', $jenis->value)
                ->first();

            if ($sudahAbsenHariIni) {
                $hasil[] = [
                    'client_uuid' => $clientUuid,
                    'status' => 'conflict_final',
                    'kode' => 'sudah_absen',
                    'pesan' => 'Anda sudah tercatat '.$jenis->value.' pada tanggal ini.',
                ];

                continue;
            }

            // 5. Validasi token QR dengan waktu koreksi scan (bukan waktu terima request)
            $validasiQr = $this->qrTokenService->validasiPayload((string) $item['token_qr'], $waktuKoreksi);
            if (! $validasiQr['valid']) {
                $hasil[] = [
                    'client_uuid' => $clientUuid,
                    'status' => 'failed',
                    'kode' => 'qr_invalid',
                    'pesan' => $validasiQr['alasan'] ?? 'QR tidak valid atau kedaluwarsa.',
                ];

                continue;
            }

            // Isolasi tenant: QR harus milik sekolah pegawai yang sama
            if ($validasiQr['sekolah_id'] !== $pegawai->sekolah_id) {
                abort(404);
            }

            // 6. Validasi radius lokasi
            $lokasiMencurigakan = false;
            $lat = isset($item['lat']) ? (float) $item['lat'] : null;
            $lng = isset($item['lng']) ? (float) $item['lng'] : null;
            $sekolah = $pegawai->sekolah;

            if ($lat !== null && $lng !== null && $sekolah !== null) {
                $lokasiMencurigakan = $this->absensiService->lokasiDiLuarRadius($lat, $lng, $sekolah);
            }

            if ($lokasiMencurigakan) {
                $perluDitinjau = true;
            }

            // 7. Hitung status kehadiran dan menit keterlambatan memakai waktu koreksi
            $hari = (int) $waktuKoreksi->format('N');
            $jamKerja = $this->statusCalculator->cariJamKerja($pegawai->sekolah_id, $pegawai->jenis ?? 'umum', $hari);
            $statusData = $jamKerja
                ? $this->statusCalculator->hitung($waktuKoreksi, $jenis, $jamKerja)
                : ['status' => null, 'menit_terlambat' => 0];

            // 8. Simpan catatan absensi
            $absensi = Absensi::create([
                'sekolah_id' => $pegawai->sekolah_id,
                'pegawai_id' => $pegawai->id,
                'tanggal' => $tanggal,
                'jenis' => $jenis->value,
                'waktu_server' => $waktuKoreksi,
                'waktu_perangkat' => $rawCaptured,
                'status' => $statusData['status']?->value,
                'menit_terlambat' => $statusData['menit_terlambat'],
                'latitude' => $lat,
                'longitude' => $lng,
                'lokasi_mencurigakan' => $lokasiMencurigakan,
                'perlu_ditinjau' => $perluDitinjau,
                'sumber' => 'offline',
                'client_uuid' => $clientUuid,
            ]);

            $hasil[] = [
                'client_uuid' => $clientUuid,
                'status' => 'synced',
                'id' => $absensi->id,
                'pesan' => 'Tersinkron.',
            ];
        }

        $responseBody = [
            'sukses' => true,
            'hasil' => $hasil,
        ];
        $statusCode = 200;

        // Simpan respon ke IdempotencyStore jika header diberikan
        if ($idempotencyKey) {
            $this->idempotencyStore->store($idempotencyKey, $sekolahId, 'api/absensi/sync', $statusCode, $responseBody);
        }

        return response()->json($responseBody, $statusCode);
    }
}
