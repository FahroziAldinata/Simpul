<?php

namespace Tests\Feature\DataInduk;

use App\Models\Pegawai;
use App\Models\Rombel;
use App\Models\Sekolah;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RombelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_operator_can_view_and_create_rombel(): void
    {
        $sekolah = Sekolah::factory()->create();

        $operator = User::factory()->create(['sekolah_id' => $sekolah->id]);
        setPermissionsTeamId($sekolah->id);
        $operator->assignRole('operator');

        $ta = TahunAjaran::factory()->create(['sekolah_id' => $sekolah->id, 'is_aktif' => true]);
        $semester = Semester::factory()->create(['sekolah_id' => $sekolah->id, 'tahun_ajaran_id' => $ta->id, 'is_aktif' => true]);

        $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id, 'selected_semester_id' => $semester->id])
            ->get(route('rombel.index'))
            ->assertOk();

        $payload = [
            'nama' => 'X RPL 1',
            'tingkat' => 10,
            'kuota' => 36,
            'is_aktif' => true,
        ];

        $response = $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id, 'selected_semester_id' => $semester->id])
            ->post(route('rombel.store'), $payload);

        $response->assertRedirect();

        $this->assertDatabaseHas('rombel', [
            'sekolah_id' => $sekolah->id,
            'semester_id' => $semester->id,
            'nama' => 'X RPL 1',
            'tingkat' => 10,
        ]);
    }

    public function test_rejects_duplicate_nama_in_same_semester(): void
    {
        $sekolah = Sekolah::factory()->create();

        $operator = User::factory()->create(['sekolah_id' => $sekolah->id]);
        setPermissionsTeamId($sekolah->id);
        $operator->assignRole('operator');

        $ta = TahunAjaran::factory()->create(['sekolah_id' => $sekolah->id, 'is_aktif' => true]);
        $semester = Semester::factory()->create(['sekolah_id' => $sekolah->id, 'tahun_ajaran_id' => $ta->id, 'is_aktif' => true]);

        Rombel::factory()->create([
            'sekolah_id' => $sekolah->id,
            'semester_id' => $semester->id,
            'nama' => 'X RPL 1',
        ]);

        $response = $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id, 'selected_semester_id' => $semester->id])
            ->post(route('rombel.store'), [
                'nama' => 'X RPL 1',
                'tingkat' => 10,
                'kuota' => 36,
            ]);

        $response->assertSessionHasErrors('nama');
    }

    public function test_rejects_duplicate_wali_kelas_in_same_semester(): void
    {
        $sekolah = Sekolah::factory()->create();

        $operator = User::factory()->create(['sekolah_id' => $sekolah->id]);
        setPermissionsTeamId($sekolah->id);
        $operator->assignRole('operator');

        $ta = TahunAjaran::factory()->create(['sekolah_id' => $sekolah->id, 'is_aktif' => true]);
        $semester = Semester::factory()->create(['sekolah_id' => $sekolah->id, 'tahun_ajaran_id' => $ta->id, 'is_aktif' => true]);

        $guru = Pegawai::factory()->create([
            'sekolah_id' => $sekolah->id,
            'jenis' => 'guru',
        ]);

        Rombel::factory()->create([
            'sekolah_id' => $sekolah->id,
            'semester_id' => $semester->id,
            'nama' => 'X RPL 1',
            'wali_kelas_id' => $guru->id,
        ]);

        $response = $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id, 'selected_semester_id' => $semester->id])
            ->post(route('rombel.store'), [
                'nama' => 'X RPL 2',
                'tingkat' => 10,
                'kuota' => 36,
                'wali_kelas_id' => $guru->id,
            ]);

        $response->assertSessionHasErrors('wali_kelas_id');
    }

    public function test_operator_cannot_delete_rombel(): void
    {
        $sekolah = Sekolah::factory()->create();

        $operator = User::factory()->create(['sekolah_id' => $sekolah->id]);
        setPermissionsTeamId($sekolah->id);
        $operator->assignRole('operator');

        $rombel = Rombel::factory()->create(['sekolah_id' => $sekolah->id]);

        $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->delete(route('rombel.destroy', $rombel->id))
            ->assertForbidden();

        $this->assertDatabaseHas('rombel', ['id' => $rombel->id]);
    }

    public function test_super_admin_can_delete_rombel(): void
    {
        $sekolah = Sekolah::factory()->create();

        setPermissionsTeamId($sekolah->id);
        $superAdmin = User::factory()->create(['sekolah_id' => null]);
        $superAdmin->assignRole('super_admin');

        $rombel = Rombel::factory()->create(['sekolah_id' => $sekolah->id]);

        $this->actingAs($superAdmin)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->delete(route('rombel.destroy', $rombel->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('rombel', ['id' => $rombel->id]);
    }

    public function test_tenant_isolation_404_when_deleting_other_school_rombel(): void
    {
        $sekolahA = Sekolah::factory()->create();
        $sekolahB = Sekolah::factory()->create();

        setPermissionsTeamId($sekolahA->id);
        $superAdmin = User::factory()->create(['sekolah_id' => null]);
        $superAdmin->assignRole('super_admin');

        $rombelB = Rombel::factory()->create([
            'sekolah_id' => $sekolahB->id,
            'nama' => 'VII-A',
        ]);

        $this->actingAs($superAdmin)
            ->withSession(['sekolah_id' => $sekolahA->id])
            ->delete(route('rombel.destroy', $rombelB->id))
            ->assertNotFound();

        $this->assertDatabaseHas('rombel', ['id' => $rombelB->id]);
    }
}
