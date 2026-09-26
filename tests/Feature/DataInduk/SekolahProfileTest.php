<?php

namespace Tests\Feature\DataInduk;

use App\Models\Sekolah;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SekolahProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        Storage::fake('s3');
    }

    public function test_super_admin_can_view_and_update_profil_sekolah_with_logo(): void
    {
        $sekolah = Sekolah::factory()->create([
            'nama' => 'SMA 1 Simpul',
            'radius_absen_meter' => 150,
        ]);

        $superAdmin = User::factory()->create(['sekolah_id' => null]);
        setPermissionsTeamId($sekolah->id);
        $superAdmin->assignRole('super_admin');

        $this->actingAs($superAdmin)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->get(route('profil-sekolah.edit'))
            ->assertOk();

        $logo = UploadedFile::fake()->image('logo-sekolah.png', 200, 200);

        $response = $this->actingAs($superAdmin)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->post(route('profil-sekolah.update'), [
                'nama' => 'SMA 1 Simpul Unggulan',
                'alamat' => 'Jl. Pendidikan No. 45',
                'latitude' => -6.2088,
                'longitude' => 106.8456,
                'radius_absen_meter' => 200,
                'kepala_sekolah' => 'Dr. H. Ahmad Dahlan, M.Pd.',
                'akreditasi' => 'A',
                'logo' => $logo,
            ]);

        $response->assertRedirect();
        $sekolah->refresh();

        $this->assertSame('SMA 1 Simpul Unggulan', $sekolah->nama);
        $this->assertSame('Jl. Pendidikan No. 45', $sekolah->alamat);
        $this->assertEquals(-6.2088, $sekolah->latitude);
        $this->assertEquals(106.8456, $sekolah->longitude);
        $this->assertSame(200, $sekolah->radius_absen_meter);
        $this->assertSame('Dr. H. Ahmad Dahlan, M.Pd.', $sekolah->kepala_sekolah);
        $this->assertSame('A', $sekolah->akreditasi);
        $this->assertNotNull($sekolah->logo_path);

        Storage::disk('s3')->assertExists($sekolah->logo_path);

        // Verifikasi audit log tercatat
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Sekolah::class,
            'subject_id' => $sekolah->id,
            'event' => 'updated',
        ]);
    }

    public function test_operator_can_view_and_update_profil_sekolah(): void
    {
        $sekolah = Sekolah::factory()->create();
        $operator = User::factory()->create(['sekolah_id' => $sekolah->id]);

        setPermissionsTeamId($sekolah->id);
        $operator->assignRole('operator');

        $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->get(route('profil-sekolah.edit'))
            ->assertOk();

        $response = $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->post(route('profil-sekolah.update'), [
                'nama' => 'Nama Sekolah Terkini',
                'alamat' => 'Alamat baru',
                'radius_absen_meter' => 180,
            ]);

        $response->assertRedirect();
        $this->assertSame('Nama Sekolah Terkini', $sekolah->refresh()->nama);
    }

    public function test_kepsek_can_view_but_cannot_update_profil_sekolah(): void
    {
        $sekolah = Sekolah::factory()->create();
        $kepsek = User::factory()->create(['sekolah_id' => $sekolah->id]);

        setPermissionsTeamId($sekolah->id);
        $kepsek->assignRole('kepsek');

        // View: OK
        $this->actingAs($kepsek)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->get(route('profil-sekolah.edit'))
            ->assertOk();

        // Update: Forbidden 403
        $this->actingAs($kepsek)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->post(route('profil-sekolah.update'), [
                'nama' => 'Nama Sekolah Ilegal',
                'radius_absen_meter' => 100,
            ])
            ->assertForbidden();
    }

    public function test_guru_cannot_view_profil_sekolah(): void
    {
        $sekolah = Sekolah::factory()->create();
        $guru = User::factory()->create(['sekolah_id' => $sekolah->id]);

        setPermissionsTeamId($sekolah->id);
        $guru->assignRole('guru');

        $this->actingAs($guru)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->get(route('profil-sekolah.edit'))
            ->assertForbidden();
    }

    public function test_cross_tenant_access_to_sekolah_returns_404(): void
    {
        $sekolahA = Sekolah::factory()->create(['nama' => 'Sekolah A']);
        $sekolahB = Sekolah::factory()->create(['nama' => 'Sekolah B']);

        $operatorA = User::factory()->create(['sekolah_id' => $sekolahA->id]);
        setPermissionsTeamId($sekolahA->id);
        $operatorA->assignRole('operator');

        // Operator Sekolah A mencoba akses endpoint spesifik Sekolah B -> 404
        $this->actingAs($operatorA)
            ->withSession(['sekolah_id' => $sekolahA->id])
            ->get(route('sekolah.edit', ['sekolah' => $sekolahB->id]))
            ->assertNotFound();

        // Operator Sekolah A mencoba update Sekolah B -> 404
        $this->actingAs($operatorA)
            ->withSession(['sekolah_id' => $sekolahA->id])
            ->post(route('sekolah.update', ['sekolah' => $sekolahB->id]), [
                'nama' => 'Hacked Name',
                'radius_absen_meter' => 100,
            ])
            ->assertNotFound();
    }
}
