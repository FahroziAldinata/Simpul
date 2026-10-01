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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AbsensiQrTest extends TestCase
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
            'nama' => 'SMK Merdeka',
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
            'nama' => 'Budi Santoso',
            'jenis' => 'guru',
        ]);

        $this->titik = TitikAbsen::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nama' => 'Gerbang Utama',
            'secret' => 'rahasia-titik-1234567890',
            'is_aktif' => true,
            'latitude' => -6.200000,
            'longitude' => 106.816666,
        ]);

        $this->qrTokenService = new QrTokenService;
    }

    public function test_scan_qr_valid_tercatat_dengan_status_hadir(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 06:50:00')); // Senin 06:50

        JamKerja::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'kelompok' => 'umum',
            'hari' => 1,
            'jam_masuk' => '07:00',
            'jam_pulang' => '15:00',
            'toleransi_menit' => 15,
            'is_libur' => false,
        ]);

        $payloadQr = $this->qrTokenService->buatPayloadQr($this->titik);

        $response = $this->actingAs($this->userGuru)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->postJson(route('absensi.scan.simpan'), [
                'payload_qr' => $payloadQr,
                'jenis' => 'masuk',
                'latitude' => -6.200050, // Dalam radius 150m
                'longitude' => 106.816670,
            ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Absensi berhasil dicatat.',
                'status' => 'hadir',
                'menit_terlambat' => 0,
                'lokasi_mencurigakan' => false,
            ]);

        $this->assertDatabaseHas('absensi', [
            'sekolah_id' => $this->sekolah->id,
            'pegawai_id' => $this->pegawai->id,
            'tanggal' => '2026-10-05',
            'jenis' => 'masuk',
            'status' => 'hadir',
            'menit_terlambat' => 0,
            'lokasi_mencurigakan' => false,
        ]);

        Carbon::setTestNow();
    }

    public function test_scan_qr_setelah_jam_masuk_plus_toleransi_tercatat_terlambat(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 07:25:00')); // Senin 07:25

        JamKerja::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'kelompok' => 'umum',
            'hari' => 1,
            'jam_masuk' => '07:00',
            'jam_pulang' => '15:00',
            'toleransi_menit' => 15,
            'is_libur' => false,
        ]);

        $payloadQr = $this->qrTokenService->buatPayloadQr($this->titik);

        $response = $this->actingAs($this->userGuru)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->postJson(route('absensi.scan.simpan'), [
                'payload_qr' => $payloadQr,
                'jenis' => 'masuk',
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'terlambat',
                'menit_terlambat' => 25,
            ]);

        $this->assertDatabaseHas('absensi', [
            'pegawai_id' => $this->pegawai->id,
            'status' => 'terlambat',
            'menit_terlambat' => 25,
        ]);

        Carbon::setTestNow();
    }

    public function test_qr_screenshot_setelah_lebih_dari_60_detik_ditolak(): void
    {
        $payloadLama = $this->qrTokenService->buatPayloadQr($this->titik);

        // Maju 70 detik (lebih dari 2 window @ 30 detik)
        Carbon::setTestNow(now()->addSeconds(70));

        $response = $this->actingAs($this->userGuru)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->postJson(route('absensi.scan.simpan'), [
                'payload_qr' => $payloadLama,
                'jenis' => 'masuk',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payload_qr']);

        $this->assertDatabaseMissing('absensi', [
            'pegawai_id' => $this->pegawai->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_satu_pegawai_tidak_bisa_absen_masuk_dua_kali_di_hari_yang_sama(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 06:50:00'));

        $payloadQr = $this->qrTokenService->buatPayloadQr($this->titik);

        // Absen pertama kali: sukses
        $response1 = $this->actingAs($this->userGuru)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->postJson(route('absensi.scan.simpan'), [
                'payload_qr' => $payloadQr,
                'jenis' => 'masuk',
            ]);
        $response1->assertOk();

        // Absen kedua kali di hari yang sama: ditolak
        $response2 = $this->actingAs($this->userGuru)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->postJson(route('absensi.scan.simpan'), [
                'payload_qr' => $payloadQr,
                'jenis' => 'masuk',
            ]);

        $response2->assertStatus(422)
            ->assertJsonValidationErrors(['jenis']);

        $this->assertEquals(1, Absensi::where('pegawai_id', $this->pegawai->id)->count());

        Carbon::setTestNow();
    }

    public function test_lokasi_di_luar_radius_ditandai_mencurigakan_tetapi_tetap_tercatat(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 06:50:00'));

        $payloadQr = $this->qrTokenService->buatPayloadQr($this->titik);

        // Koordinat Monas (jauh > 10 km dari sekolah)
        $response = $this->actingAs($this->userGuru)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->postJson(route('absensi.scan.simpan'), [
                'payload_qr' => $payloadQr,
                'jenis' => 'masuk',
                'latitude' => -6.175392,
                'longitude' => 106.827153,
            ]);

        // Tetap berhasil tercatat (200 OK, bukan 422/403)
        $response->assertOk()
            ->assertJson([
                'lokasi_mencurigakan' => true,
            ]);

        $this->assertDatabaseHas('absensi', [
            'pegawai_id' => $this->pegawai->id,
            'lokasi_mencurigakan' => true,
            'perlu_ditinjau' => true,
        ]);

        Carbon::setTestNow();
    }

    public function test_pegawai_sekolah_a_scan_qr_titik_absen_sekolah_b_ditolak_404_isolasi_tenant(): void
    {
        $sekolahB = Sekolah::factory()->create();
        $titikB = TitikAbsen::factory()->create([
            'sekolah_id' => $sekolahB->id,
            'is_aktif' => true,
        ]);

        $payloadSekolahB = $this->qrTokenService->buatPayloadQr($titikB);

        $response = $this->actingAs($this->userGuru)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->postJson(route('absensi.scan.simpan'), [
                'payload_qr' => $payloadSekolahB,
                'jenis' => 'masuk',
            ]);

        // Isolasi tenant: 404
        $response->assertStatus(404);
        $this->assertDatabaseMissing('absensi', [
            'pegawai_id' => $this->pegawai->id,
        ]);
    }
}
