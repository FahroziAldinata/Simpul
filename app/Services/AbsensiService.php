<?php

namespace App\Services;

use App\Enums\JenisAbsensi;
use App\Models\Absensi;
use App\Models\Pegawai;
use App\Models\Sekolah;
use App\Services\IzinApprovalService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * AbsensiService — orkestrasi absensi QR dan manual.
 *
 * Bertanggung jawab atas:
 *  1. Proses absen via QR (T-08.05) — validasi token, cek GPS, hitung status, simpan
 *  2. Proses absen manual oleh Operator (T-08.08) — validasi batas 7 hari, audit log wajib
 *  3. Validasi radius GPS (T-08.07) — flag lokasi_mencurigakan, bukan menolak absen
 */
class AbsensiService
{
    /**
     * Batas maksimal absen manual ke belakang dalam hari (Keputusan Minggu 8 #4).
     */
    public const BATAS_MANUAL_HARI = 7;

    public function __construct(
        private readonly QrTokenService $qrTokenService,
        private readonly StatusKehadiranCalculator $statusCalculator,
        private readonly IzinApprovalService $izinApprovalService,
    ) {}

    /**
     * Proses absensi via QR scan.
     *
     * @param  array{
     *     payload_qr: string,
     *     jenis: string,
     *     latitude: float|null,
     *     longitude: float|null,
     *     waktu_perangkat: string|null,
     *     client_uuid: string|null,
     * } $data
     *
     * @throws ValidationException Jika token QR tidak valid atau sudah absen
     */
    public function prosesAbsenQr(Pegawai $pegawai, array $data): Absensi
    {
        // 1. Validasi token QR
        $hasil = $this->qrTokenService->validasiPayload($data['payload_qr']);
        if (! $hasil['valid']) {
            throw ValidationException::withMessages([
                'payload_qr' => $hasil['alasan'] ?? 'QR tidak valid.',
            ]);
        }

        // Pastikan QR milik sekolah yang sama dengan pegawai (isolasi tenant = 404)
        abort_if($hasil['sekolah_id'] !== $pegawai->sekolah_id, 404);

        $jenis = JenisAbsensi::from($data['jenis']);
        $tanggal = now()->toDateString();
        $sekolah = $pegawai->sekolah;

        // Guard T-09.05: Tolak scan jika pegawai sudah punya izin disetujui hari ini
        // Izin formal tidak boleh diam-diam ditimpa oleh scan QR.
        $this->izinApprovalService->pastikanTidakAdaIzinDisetujui($pegawai, $tanggal);

        // 2. Cek duplikat — unique constraint ada di DB, tapi beri pesan manusiawi
        $sudahAbsen = Absensi::where('pegawai_id', $pegawai->id)
            ->where('tanggal', $tanggal)
            ->where('jenis', $jenis->value)
            ->exists();

        if ($sudahAbsen) {
            throw ValidationException::withMessages([
                'jenis' => 'Anda sudah tercatat '.$jenis->value.' hari ini.',
            ]);
        }

        // 3. Validasi radius GPS (T-08.07) — tandai mencurigakan, TIDAK tolak
        $lokasiMencurigakan = false;
        $lat = $data['latitude'] ?? null;
        $lng = $data['longitude'] ?? null;
        if ($lat !== null && $lng !== null && $sekolah !== null) {
            $lokasiMencurigakan = $this->lokasiDiLuarRadius(
                (float) $lat,
                (float) $lng,
                $sekolah,
            );
        }

        // 4. Hitung status kehadiran
        $waktuServer = now();
        $hari = (int) $waktuServer->format('N'); // 1=Senin, 7=Minggu
        $jamKerja = $this->statusCalculator->cariJamKerja(
            $pegawai->sekolah_id,
            $pegawai->jenis ?? 'umum',
            $hari
        );

        $statusData = $jamKerja
            ? $this->statusCalculator->hitung($waktuServer, $jenis, $jamKerja)
            : ['status' => null, 'menit_terlambat' => 0];

        // 5. Simpan
        return Absensi::create([
            'sekolah_id' => $pegawai->sekolah_id,
            'pegawai_id' => $pegawai->id,
            'tanggal' => $tanggal,
            'jenis' => $jenis->value,
            'waktu_server' => $waktuServer,
            'waktu_perangkat' => $data['waktu_perangkat'] ?? null,
            'status' => $statusData['status']?->value,
            'menit_terlambat' => $statusData['menit_terlambat'],
            'latitude' => $lat,
            'longitude' => $lng,
            'lokasi_mencurigakan' => $lokasiMencurigakan,
            'perlu_ditinjau' => $lokasiMencurigakan,
            'sumber' => 'qr',
            'client_uuid' => $data['client_uuid'] ?? null,
        ]);
    }

    /**
     * Proses absen manual oleh Operator (T-08.08).
     *
     * Wajib: alasan terisi, tidak bisa lebih dari BATAS_MANUAL_HARI hari ke belakang.
     * Audit log tercatat otomatis via LogsSimpulActivity (spatie/activitylog).
     *
     * @param  array{
     *     pegawai_id: string,
     *     tanggal: string,
     *     jenis: string,
     *     alasan_manual: string,
     * } $data
     *
     * @throws ValidationException Jika tanggal terlalu lampau atau sudah absen
     */
    public function prosesAbsenManual(Pegawai $pegawai, array $data, int $pencatatId): Absensi
    {
        $tanggal = Carbon::parse($data['tanggal'])->startOfDay();
        $today = now()->startOfDay();

        // Tanggal tidak boleh di masa depan
        if ($tanggal->gt($today)) {
            throw ValidationException::withMessages([
                'tanggal' => 'Tidak bisa mencatat absen untuk tanggal yang belum terjadi.',
            ]);
        }

        // Cek batas 7 hari ke belakang (Keputusan #4)
        if ($tanggal->lt($today->copy()->subDays(self::BATAS_MANUAL_HARI))) {
            throw ValidationException::withMessages([
                'tanggal' => 'Absen manual hanya bisa dilakukan untuk '.self::BATAS_MANUAL_HARI.' hari terakhir.',
            ]);
        }

        $jenis = JenisAbsensi::from($data['jenis']);

        // Cek duplikat
        $sudahAbsen = Absensi::where('pegawai_id', $pegawai->id)
            ->where('tanggal', $tanggal->toDateString())
            ->where('jenis', $jenis->value)
            ->exists();

        if ($sudahAbsen) {
            throw ValidationException::withMessages([
                'jenis' => 'Pegawai sudah tercatat '.$jenis->value.' pada tanggal tersebut.',
            ]);
        }

        $waktuServer = now();
        $hari = (int) $tanggal->format('N');
        $jamKerja = $this->statusCalculator->cariJamKerja(
            $pegawai->sekolah_id,
            $pegawai->jenis ?? 'umum',
            $hari
        );

        // Untuk absen manual, status dihitung dari jam_masuk hari itu vs waktu sekarang
        // (waktu pencatatan, bukan waktu kejadian — ini disebut eksplisit di audit log)
        $statusData = $jamKerja
            ? $this->statusCalculator->hitung($waktuServer, $jenis, $jamKerja)
            : ['status' => null, 'menit_terlambat' => 0];

        // Simpan — audit log otomatis via LogsSimpulActivity mencatat siapa mencatat untuk siapa
        return Absensi::create([
            'sekolah_id' => $pegawai->sekolah_id,
            'pegawai_id' => $pegawai->id,
            'tanggal' => $tanggal->toDateString(),
            'jenis' => $jenis->value,
            'waktu_server' => $waktuServer,
            'status' => $statusData['status']?->value,
            'menit_terlambat' => $statusData['menit_terlambat'],
            'sumber' => 'manual',
            'dicatat_oleh' => $pencatatId,
            'alasan_manual' => $data['alasan_manual'],
        ]);
    }

    /**
     * Validasi apakah koordinat berada di luar radius sekolah (T-08.07).
     *
     * Menggunakan Haversine formula. Jika di luar radius, tandai lokasi_mencurigakan = true,
     * tapi absensi TETAP dicatat (sesuai PRD 6.2: tidak menolak).
     */
    public function lokasiDiLuarRadius(float $lat, float $lng, Sekolah $sekolah): bool
    {
        if ($sekolah->latitude === null || $sekolah->longitude === null) {
            return false; // Koordinat sekolah belum dikonfigurasi — tidak bisa memvalidasi
        }

        $radius = $sekolah->radius_absen_meter ?? 150;
        $jarak = $this->hitungJarakMeter(
            (float) $sekolah->latitude,
            (float) $sekolah->longitude,
            $lat,
            $lng,
        );

        return $jarak > $radius;
    }

    /**
     * Haversine formula — hitung jarak dua koordinat GPS dalam meter.
     */
    private function hitungJarakMeter(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // meter

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
