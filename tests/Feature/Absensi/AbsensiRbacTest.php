<?php

namespace Tests\Feature\Absensi;

use App\Models\Sekolah;
use App\Models\TitikAbsen;
use App\Models\User;
use App\Services\QrTokenService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbsensiRbacTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private TitikAbsen $titik;

    private QrTokenService $qrTokenService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->sekolah = Sekolah::factory()->create();
        setPermissionsTeamId($this->sekolah->id);

        $this->titik = TitikAbsen::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'is_aktif' => true,
        ]);

        $this->qrTokenService = new QrTokenService;
    }

    /**
     * Matriks PRD 4.2: Absensi diri sendiri dapat diakses oleh semua role KECUALI Orang Tua.
     */
    public function test_semua_role_kecuali_orang_tua_dapat_mengakses_halaman_scan_absensi(): void
    {
        $allowedRoles = ['super_admin', 'operator', 'kepsek', 'guru', 'wali_kelas'];

        foreach ($allowedRoles as $role) {
            $user = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
            $user->assignRole($role);

            $response = $this->actingAs($user)
                ->withSession(['sekolah_id' => $this->sekolah->id])
                ->get(route('absensi.scan'));

            $response->assertOk();
        }
    }

    public function test_orang_tua_ditolak_mengakses_halaman_scan_absensi(): void
    {
        $userOrangTua = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $userOrangTua->assignRole('orang_tua');

        $response = $this->actingAs($userOrangTua)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('absensi.scan'));

        $response->assertStatus(403);
    }

    public function test_orang_tua_ditolak_mengirimkan_absensi_qr(): void
    {
        $userOrangTua = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $userOrangTua->assignRole('orang_tua');

        $payload = $this->qrTokenService->buatPayloadQr($this->titik);

        $response = $this->actingAs($userOrangTua)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->postJson(route('absensi.scan.simpan'), [
                'payload_qr' => $payload,
                'jenis' => 'masuk',
            ]);

        $response->assertStatus(403);
    }
}
