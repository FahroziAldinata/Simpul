<?php

namespace Tests\Feature\Absensi;

use App\Models\Absensi;
use App\Models\JamKerja;
use App\Models\Pegawai;
use App\Models\Sekolah;
use App\Models\TitikAbsen;
use App\Models\User;
use App\Services\QrTokenService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class AbsensiSyncIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $userGuru;

    private Pegawai $pegawai;

    private TitikAbsen $titik;

    private QrTokenService $qrTokenService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->sekolah = Sekolah::factory()->create([
            'nama' => 'SMK Negeri 1 Idempoten',
            'latitude' => -6.200000,
            'longitude' => 106.816666,
            'radius_absen_meter' => 150,
        ]);

        setPermissionsTeamId($this->sekolah->id);

        $this->userGuru = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $this->userGuru->assignRole('guru');

        $this->pegawai = Pegawai::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'user_id' => $this->userGuru->id,
            'nama' => 'Siti Guru',
            'jenis' => 'guru',
        ]);

        $this->titik = TitikAbsen::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nama' => 'Pintu Masuk Guru',
            'secret' => 'kunci-rahasia-titik-guru-98765',
            'is_aktif' => true,
            'latitude' => -6.200000,
            'longitude' => 106.816666,
        ]);

        JamKerja::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'kelompok' => 'guru',
            'hari' => 1,
            'jam_masuk' => '07:00',
            'jam_pulang' => '15:00',
            'toleransi_menit' => 15,
            'is_libur' => false,
        ]);

        $this->qrTokenService = new QrTokenService;
    }

    /**
     * T-10.11: Exit Criteria Utama PRD 6.2 — Sync 5x dengan payload identik menghasilkan tepat 1 baris di DB.
     */
    public function test_sync_5x_dengan_payload_identik_menghasilkan_tepat_1_baris_di_database(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 06:45:00'));

        $payloadQr = $this->qrTokenService->buatPayloadQr($this->titik);
        $clientUuid = (string) Str::uuid();
        $idempotencyKey = 'idemp-req-'.$clientUuid;
        $capturedMs = now()->getTimestampMs();

        $payload = [
            'items' => [
                [
                    'client_uuid' => $clientUuid,
                    'token_qr' => $payloadQr,
                    'jenis' => 'masuk',
                    'captured_at' => $capturedMs,
                    'clock_offset' => 0,
                    'lat' => -6.200010,
                    'lng' => 106.816670,
                ],
            ],
        ];

        // Jalankan sinkronisasi 5 KALI berturut-turut
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->actingAs($this->userGuru)
                ->withHeader('Idempotency-Key', $idempotencyKey)
                ->withoutMiddleware(ValidateCsrfToken::class)
                ->postJson(route('absensi.sync'), $payload);

            $response->assertOk()
                ->assertJsonPath('sukses', true)
                ->assertJsonPath('hasil.0.client_uuid', $clientUuid)
                ->assertJsonPath('hasil.0.status', 'synced');
        }

        // Bukti mutlak: database absensi HANYA memiliki tepat 1 baris
        $this->assertEquals(
            1,
            Absensi::where('client_uuid', $clientUuid)->count(),
            'Tepat 1 baris absensi untuk client_uuid ini'
        );

        $this->assertEquals(
            1,
            Absensi::where('pegawai_id', $this->pegawai->id)->count(),
            'Tepat 1 baris absensi untuk pegawai ini'
        );
    }

    /**
     * T-10.05 / T-10.09: captured_at dipakai untuk validasi window TOTP (bukan waktu terima request).
     */
    public function test_sync_menggunakan_captured_at_untuk_validasi_qr_window(): void
    {
        // 1. Scan dilakukan pada 06:40:00 saat offline
        Carbon::setTestNow(Carbon::parse('2026-10-05 06:40:00'));
        $payloadQr = $this->qrTokenService->buatPayloadQr($this->titik);
        $capturedMs = Carbon::parse('2026-10-05 06:40:05')->getTimestampMs();

        // 2. Perangkat baru online dan sync dijalankan pada 07:30:00 (50 menit kemudian!)
        Carbon::setTestNow(Carbon::parse('2026-10-05 07:30:00'));

        $clientUuid = (string) Str::uuid();

        $response = $this->actingAs($this->userGuru)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson(route('absensi.sync'), [
                'items' => [
                    [
                        'client_uuid' => $clientUuid,
                        'token_qr' => $payloadQr,
                        'jenis' => 'masuk',
                        'captured_at' => $capturedMs,
                        'clock_offset' => 0,
                        'lat' => -6.200000,
                        'lng' => 106.816666,
                    ],
                ],
            ]);

        // Harus berhasil karena captured_at (06:40:05) sesuai dengan window pembuatan QR
        $response->assertOk()
            ->assertJsonPath('hasil.0.status', 'synced');

        $this->assertDatabaseHas('absensi', [
            'client_uuid' => $clientUuid,
            'status' => 'hadir',
        ]);
    }

    /**
     * T-10.09: Deteksi clock drift > 12 jam menandai perlu_ditinjau = true.
     */
    public function test_clock_drift_lebih_dari_12_jam_menandai_perlu_ditinjau(): void
    {
        // Server menerima jam 20:00:00
        Carbon::setTestNow(Carbon::parse('2026-10-05 20:00:00'));

        // QR dibuat dan discan 13 jam yang lalu (07:00:00)
        $waktuScan = Carbon::parse('2026-10-05 07:00:00');
        Carbon::setTestNow($waktuScan);
        $payloadQr = $this->qrTokenService->buatPayloadQr($this->titik);

        // Kembali ke waktu server saat sync (20:00:00, selisih 13 jam)
        Carbon::setTestNow(Carbon::parse('2026-10-05 20:00:00'));

        $clientUuid = (string) Str::uuid();

        $response = $this->actingAs($this->userGuru)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson(route('absensi.sync'), [
                'items' => [
                    [
                        'client_uuid' => $clientUuid,
                        'token_qr' => $payloadQr,
                        'jenis' => 'masuk',
                        'captured_at' => $waktuScan->getTimestampMs(),
                        'clock_offset' => 0,
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('hasil.0.status', 'synced');

        $this->assertDatabaseHas('absensi', [
            'client_uuid' => $clientUuid,
            'perlu_ditinjau' => true,
        ]);
    }

    /**
     * Keputusan #2: Interaksi sync offline dengan guard izin disetujui.
     * Mengembalikan conflict_final dan tidak retry loop.
     */
    public function test_guard_izin_disetujui_menolak_sync_dengan_conflict_final(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 07:00:00'));
        $payloadQr = $this->qrTokenService->buatPayloadQr($this->titik);

        // Simulasi: Ada izin yang sudah disetujui pada tanggal 2026-10-05
        Absensi::create([
            'sekolah_id' => $this->sekolah->id,
            'pegawai_id' => $this->pegawai->id,
            'tanggal' => '2026-10-05',
            'jenis' => 'masuk',
            'status' => 'izin',
            'sumber' => 'izin',
            'waktu_server' => Carbon::parse('2026-10-05 06:00:00'),
        ]);

        $clientUuid = (string) Str::uuid();

        $response = $this->actingAs($this->userGuru)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson(route('absensi.sync'), [
                'items' => [
                    [
                        'client_uuid' => $clientUuid,
                        'token_qr' => $payloadQr,
                        'jenis' => 'masuk',
                        'captured_at' => now()->getTimestampMs(),
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('hasil.0.status', 'conflict_final')
            ->assertJsonPath('hasil.0.kode', 'izin_disetujui')
            ->assertJsonPath('hasil.0.client_uuid', $clientUuid);

        // Absensi baru TIDAK disimpan di database (tidak menduplikasi atau menimpa izin)
        $this->assertDatabaseMissing('absensi', [
            'client_uuid' => $clientUuid,
        ]);
    }

    /**
     * Keputusan #1: Isolasi tenant — QR sekolah lain ditolak 404.
     */
    public function test_isolasi_tenant_menolak_qr_milik_sekolah_lain(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 06:50:00'));

        $sekolahLain = Sekolah::factory()->create([
            'nama' => 'Sekolah Tetangga',
        ]);

        $titikSekolahLain = TitikAbsen::factory()->create([
            'sekolah_id' => $sekolahLain->id,
            'secret' => 'secret-sekolah-lain-xyz123',
            'is_aktif' => true,
        ]);

        $payloadQrLain = $this->qrTokenService->buatPayloadQr($titikSekolahLain);
        $clientUuid = (string) Str::uuid();

        $response = $this->actingAs($this->userGuru)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson(route('absensi.sync'), [
                'items' => [
                    [
                        'client_uuid' => $clientUuid,
                        'token_qr' => $payloadQrLain,
                        'jenis' => 'masuk',
                        'captured_at' => now()->getTimestampMs(),
                    ],
                ],
            ]);

        // Harus 404 per isolasi multi-tenant SIMPUL
        $response->assertNotFound();
    }
}
