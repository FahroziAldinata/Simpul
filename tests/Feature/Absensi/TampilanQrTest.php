<?php

namespace Tests\Feature\Absensi;

use App\Models\Sekolah;
use App\Models\TitikAbsen;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TampilanQrTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $operator;

    private User $guru;

    private TitikAbsen $titik;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->sekolah = Sekolah::factory()->create();
        setPermissionsTeamId($this->sekolah->id);

        $this->operator = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $this->operator->assignRole('operator');

        $this->guru = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $this->guru->assignRole('guru');

        $this->titik = TitikAbsen::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nama' => 'Lobby Utama',
            'is_aktif' => true,
        ]);
    }

    public function test_operator_dapat_mengakses_halaman_tampilan_qr(): void
    {
        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('absensi.qr.tampil', $this->titik->id));

        $response->assertOk();
    }

    public function test_guru_tidak_dapat_mengakses_halaman_tampilan_qr(): void
    {
        $response = $this->actingAs($this->guru)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('absensi.qr.tampil', $this->titik->id));

        // Akses halaman tampilan QR dibatasi hanya operator dan super_admin (Keputusan #2)
        $response->assertStatus(403);
    }

    public function test_endpoint_token_qr_mengembalikan_token_dan_sisa_detik_yang_valid(): void
    {
        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->getJson(route('absensi.titik.token', $this->titik->id));

        $response->assertOk()
            ->assertJsonStructure([
                'payload',
                'sisa_detik',
                'window_seconds',
            ]);

        $this->assertNotEmpty($response->json('payload'));
        $this->assertEquals(30, $response->json('window_seconds'));
    }

    public function test_endpoint_token_qr_menolak_titik_absen_tenant_lain(): void
    {
        $sekolahLain = Sekolah::factory()->create();
        $titikLain = TitikAbsen::factory()->create([
            'sekolah_id' => $sekolahLain->id,
            'is_aktif' => true,
        ]);

        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->getJson(route('absensi.titik.token', $titikLain->id));

        // Tenant isolation: 404
        $response->assertStatus(404);
    }
}
