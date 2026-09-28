<?php

namespace Tests\Feature\DataInduk;

use App\Models\Ruang;
use App\Models\Sekolah;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RuangTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_operator_can_view_and_create_ruang(): void
    {
        $sekolah = Sekolah::factory()->create();

        $operator = User::factory()->create(['sekolah_id' => $sekolah->id]);
        setPermissionsTeamId($sekolah->id);
        $operator->assignRole('operator');

        $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->get(route('ruang.index'))
            ->assertOk();

        $payload = [
            'kode' => 'LAB-KOM-1',
            'nama' => 'Laboratorium Komputer 1',
            'kategori' => 'laboratorium',
            'kapasitas' => 36,
            'lokasi' => 'Gedung B Lt. 2',
            'is_aktif' => true,
        ];

        $response = $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->post(route('ruang.store'), $payload);

        $response->assertRedirect();

        $this->assertDatabaseHas('ruang', [
            'sekolah_id' => $sekolah->id,
            'kode' => 'LAB-KOM-1',
            'kategori' => 'laboratorium',
            'kapasitas' => 36,
        ]);
    }

    public function test_rejects_duplicate_ruang_kode_within_same_school(): void
    {
        $sekolah = Sekolah::factory()->create();

        $operator = User::factory()->create(['sekolah_id' => $sekolah->id]);
        setPermissionsTeamId($sekolah->id);
        $operator->assignRole('operator');

        Ruang::factory()->create([
            'sekolah_id' => $sekolah->id,
            'kode' => 'R.101',
        ]);

        $response = $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->post(route('ruang.store'), [
                'kode' => 'R.101',
                'nama' => 'Ruang 101 Duplikat',
                'kategori' => 'kelas',
                'kapasitas' => 30,
            ]);

        $response->assertSessionHasErrors('kode');
    }

    public function test_operator_cannot_delete_ruang(): void
    {
        $sekolah = Sekolah::factory()->create();

        $operator = User::factory()->create(['sekolah_id' => $sekolah->id]);
        setPermissionsTeamId($sekolah->id);
        $operator->assignRole('operator');

        $ruang = Ruang::factory()->create(['sekolah_id' => $sekolah->id]);

        $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->delete(route('ruang.destroy', $ruang->id))
            ->assertForbidden();

        $this->assertDatabaseHas('ruang', ['id' => $ruang->id]);
    }

    public function test_super_admin_can_delete_ruang(): void
    {
        $sekolah = Sekolah::factory()->create();

        setPermissionsTeamId($sekolah->id);
        $superAdmin = User::factory()->create(['sekolah_id' => null]);
        $superAdmin->assignRole('super_admin');

        $ruang = Ruang::factory()->create(['sekolah_id' => $sekolah->id]);

        $this->actingAs($superAdmin)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->delete(route('ruang.destroy', $ruang->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('ruang', ['id' => $ruang->id]);
    }

    public function test_tenant_isolation_404_when_deleting_other_school_ruang(): void
    {
        $sekolahA = Sekolah::factory()->create();
        $sekolahB = Sekolah::factory()->create();

        setPermissionsTeamId($sekolahA->id);
        $superAdmin = User::factory()->create(['sekolah_id' => null]);
        $superAdmin->assignRole('super_admin');

        $ruangB = Ruang::factory()->create([
            'sekolah_id' => $sekolahB->id,
            'kode' => 'R.B-99',
        ]);

        $this->actingAs($superAdmin)
            ->withSession(['sekolah_id' => $sekolahA->id])
            ->delete(route('ruang.destroy', $ruangB->id))
            ->assertNotFound();

        $this->assertDatabaseHas('ruang', ['id' => $ruangB->id]);
    }
}
