<?php

namespace Tests\Feature\DataInduk;

use App\Models\MataPelajaran;
use App\Models\Sekolah;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MataPelajaranTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_operator_can_view_and_create_mata_pelajaran(): void
    {
        $sekolah = Sekolah::factory()->create();

        $operator = User::factory()->create(['sekolah_id' => $sekolah->id]);
        setPermissionsTeamId($sekolah->id);
        $operator->assignRole('operator');

        $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->get(route('mata-pelajaran.index'))
            ->assertOk();

        $payload = [
            'kode' => 'MTK',
            'nama' => 'Matematika',
            'kelompok' => 'umum_a',
            'tingkat' => 10,
            'bobot_beban_kognitif' => 'berat',
            'butuh_ruang_kategori' => null,
            'is_aktif' => true,
        ];

        $response = $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->post(route('mata-pelajaran.store'), $payload);

        $response->assertRedirect();

        $this->assertDatabaseHas('mata_pelajaran', [
            'sekolah_id' => $sekolah->id,
            'kode' => 'MTK',
            'bobot_beban_kognitif' => 'berat',
        ]);
    }

    public function test_rejects_duplicate_kode_within_same_school(): void
    {
        $sekolah = Sekolah::factory()->create();

        $operator = User::factory()->create(['sekolah_id' => $sekolah->id]);
        setPermissionsTeamId($sekolah->id);
        $operator->assignRole('operator');

        MataPelajaran::factory()->create([
            'sekolah_id' => $sekolah->id,
            'kode' => 'BIN',
        ]);

        $response = $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->post(route('mata-pelajaran.store'), [
                'kode' => 'BIN',
                'nama' => 'Bahasa Indonesia Duplikat',
                'kelompok' => 'umum_a',
                'bobot_beban_kognitif' => 'sedang',
            ]);

        $response->assertSessionHasErrors('kode');
    }

    public function test_operator_cannot_delete_mapel(): void
    {
        $sekolah = Sekolah::factory()->create();

        $operator = User::factory()->create(['sekolah_id' => $sekolah->id]);
        setPermissionsTeamId($sekolah->id);
        $operator->assignRole('operator');

        $mapel = MataPelajaran::factory()->create(['sekolah_id' => $sekolah->id]);

        $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->delete(route('mata-pelajaran.destroy', $mapel->id))
            ->assertForbidden();

        $this->assertDatabaseHas('mata_pelajaran', ['id' => $mapel->id]);
    }

    public function test_super_admin_can_delete_mapel(): void
    {
        $sekolah = Sekolah::factory()->create();

        setPermissionsTeamId($sekolah->id);
        $superAdmin = User::factory()->create(['sekolah_id' => null]);
        $superAdmin->assignRole('super_admin');

        $mapel = MataPelajaran::factory()->create(['sekolah_id' => $sekolah->id]);

        $this->actingAs($superAdmin)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->delete(route('mata-pelajaran.destroy', $mapel->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('mata_pelajaran', ['id' => $mapel->id]);
    }

    public function test_tenant_isolation_404_when_deleting_other_school_mapel(): void
    {
        $sekolahA = Sekolah::factory()->create();
        $sekolahB = Sekolah::factory()->create();

        setPermissionsTeamId($sekolahA->id);
        $superAdmin = User::factory()->create(['sekolah_id' => null]);
        $superAdmin->assignRole('super_admin');

        $mapelB = MataPelajaran::factory()->create([
            'sekolah_id' => $sekolahB->id,
            'kode' => 'BIO-B',
        ]);

        $this->actingAs($superAdmin)
            ->withSession(['sekolah_id' => $sekolahA->id])
            ->delete(route('mata-pelajaran.destroy', $mapelB->id))
            ->assertNotFound();

        $this->assertDatabaseHas('mata_pelajaran', ['id' => $mapelB->id]);
    }
}
