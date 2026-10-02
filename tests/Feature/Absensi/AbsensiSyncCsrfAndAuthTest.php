<?php

namespace Tests\Feature\Absensi;

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

class AbsensiSyncCsrfAndAuthTest extends TestCase
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
            'nama' => 'SMK Negeri 1 Offline',
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
            'nama' => 'Ahmad Guru',
            'jenis' => 'guru',
        ]);

        $this->titik = TitikAbsen::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nama' => 'Pos Satpam',
            'secret' => 'rahasia-titik-offline-12345',
            'is_aktif' => true,
            'latitude' => -6.200000,
            'longitude' => 106.816666,
        ]);

        $this->qrTokenService = new QrTokenService;
    }

    public function test_endpoint_sync_menolak_request_tanpa_sesi_auth_dengan_401_meskipun_tanpa_csrf(): void
    {
        // Request dari tamu tanpa sesi login (unauthenticated)
        $response = $this->postJson(route('absensi.sync'), [
            'items' => [
                [
                    'client_uuid' => (string) Str::uuid(),
                    'token_qr' => 'dummy-qr',
                    'jenis' => 'masuk',
                    'captured_at' => now()->getTimestampMs(),
                ],
            ],
        ]);

        // Harus ditolak 401 Unauthorized — membuktikan pengecualian CSRF tidak membuka endpoint ke publik
        $response->assertUnauthorized();
    }

    public function test_endpoint_sync_dapat_diakses_tanpa_csrf_token_jika_memiliki_sesi_auth(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 06:45:00'));

        JamKerja::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'kelompok' => 'guru',
            'hari' => 1,
            'jam_masuk' => '07:00',
            'jam_pulang' => '15:00',
            'toleransi_menit' => 15,
            'is_libur' => false,
        ]);

        $payloadQr = $this->qrTokenService->buatPayloadQr($this->titik);
        $clientUuid = (string) Str::uuid();

        // Request dengan sesi auth aktif tapi sengaja tanpa header X-CSRF-TOKEN / token CSRF
        $response = $this->actingAs($this->userGuru)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson(route('absensi.sync'), [
                'items' => [
                    [
                        'client_uuid' => $clientUuid,
                        'token_qr' => $payloadQr,
                        'jenis' => 'masuk',
                        'captured_at' => now()->getTimestampMs(),
                        'clock_offset' => 0,
                        'lat' => -6.200010,
                        'lng' => 106.816670,
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('sukses', true)
            ->assertJsonPath('hasil.0.client_uuid', $clientUuid)
            ->assertJsonPath('hasil.0.status', 'synced');

        $this->assertDatabaseHas('absensi', [
            'client_uuid' => $clientUuid,
            'pegawai_id' => $this->pegawai->id,
            'sekolah_id' => $this->sekolah->id,
            'sumber' => 'offline',
        ]);
    }
}
