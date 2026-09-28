<?php

namespace Tests\Feature\DataInduk;

use App\Models\Ruang;
use App\Models\Sekolah;
use App\Models\TahunAjaran;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataIndukRbacTest extends TestCase
{
    use RefreshDatabase;

    protected Sekolah $sekolah;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->sekolah = Sekolah::factory()->create(['jenjang' => 'sma']);
        setPermissionsTeamId($this->sekolah->id);
    }

    private function createUserWithRole(string $roleName): User
    {
        $user = User::factory()->create([
            'sekolah_id' => $roleName === 'super_admin' ? null : $this->sekolah->id,
        ]);
        setPermissionsTeamId($this->sekolah->id);
        $user->assignRole($roleName);

        return $user;
    }

    public function test_super_admin_has_full_crud_access(): void
    {
        $user = $this->createUserWithRole('super_admin');

        // View: OK
        $this->actingAs($user)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('tahun-ajaran.index'))
            ->assertOk();

        // Create: OK
        $this->actingAs($user)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('tahun-ajaran.store'), [
                'nama' => '2026/2027',
                'tanggal_mulai' => '2026-07-15',
                'tanggal_selesai' => '2027-06-20',
            ])
            ->assertRedirect();

        $ta = TahunAjaran::where('sekolah_id', $this->sekolah->id)->firstOrFail();

        // Update: OK
        $this->actingAs($user)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->put(route('tahun-ajaran.update', $ta->id), [
                'nama' => '2026/2027 Revisi',
                'tanggal_mulai' => '2026-07-15',
                'tanggal_selesai' => '2027-06-20',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tahun_ajaran', [
            'id' => $ta->id,
            'nama' => '2026/2027 Revisi',
        ]);

        // Delete: OK (Super Admin has delete permission)
        $this->actingAs($user)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->delete(route('tahun-ajaran.destroy', $ta->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('tahun_ajaran', ['id' => $ta->id]);
    }

    public function test_operator_has_cru_access_to_data_induk(): void
    {
        $user = $this->createUserWithRole('operator');

        // View: OK (200)
        $this->actingAs($user)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('ruang.index'))
            ->assertOk();

        // Create: OK (302 redirect)
        $this->actingAs($user)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('ruang.store'), [
                'kode' => 'R-RBAC',
                'nama' => 'Ruang RBAC Operator',
                'kategori' => 'kelas',
                'kapasitas' => 32,
            ])
            ->assertRedirect();

        $ruang = Ruang::where('sekolah_id', $this->sekolah->id)->where('kode', 'R-RBAC')->firstOrFail();

        // Update: OK (302 redirect)
        $this->actingAs($user)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->put(route('ruang.update', $ruang->id), [
                'kode' => 'R-RBAC',
                'nama' => 'Ruang RBAC Updated',
                'kategori' => 'kelas',
                'kapasitas' => 36,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ruang', [
            'id' => $ruang->id,
            'nama' => 'Ruang RBAC Updated',
            'kapasitas' => 36,
        ]);
    }

    public function test_operator_cannot_delete_data_induk_and_receives_403(): void
    {
        $user = $this->createUserWithRole('operator');

        $ruang = Ruang::factory()->create(['sekolah_id' => $this->sekolah->id]);

        // Delete: Forbidden (403)
        $this->actingAs($user)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->delete(route('ruang.destroy', $ruang->id))
            ->assertForbidden();

        $this->assertDatabaseHas('ruang', ['id' => $ruang->id]);
    }

    public function test_kepsek_can_only_view_and_cannot_create_or_mutate_data_induk(): void
    {
        $user = $this->createUserWithRole('kepsek');

        // View: OK
        $this->actingAs($user)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('ruang.index'))
            ->assertOk();

        // Create: Forbidden (403)
        $this->actingAs($user)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('ruang.store'), [
                'kode' => 'R-KEPSEK',
                'nama' => 'Ruang Kepsek',
                'kategori' => 'kelas',
                'kapasitas' => 32,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('ruang', ['kode' => 'R-KEPSEK']);
    }

    public function test_waka_kurikulum_can_only_view_and_cannot_create_or_mutate_data_induk(): void
    {
        $user = $this->createUserWithRole('waka_kurikulum');

        // View: OK
        $this->actingAs($user)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('mata-pelajaran.index'))
            ->assertOk();

        // Create: Forbidden (403)
        $this->actingAs($user)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('mata-pelajaran.store'), [
                'kode' => 'MP-WAKA',
                'nama' => 'Mapel Waka',
                'kelompok' => 'umum_a',
                'bobot_beban_kognitif' => 'sedang',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('mata_pelajaran', ['kode' => 'MP-WAKA']);
    }

    public function test_guru_has_no_access_to_data_induk_and_receives_403(): void
    {
        $user = $this->createUserWithRole('guru');

        // View: Forbidden (403)
        $this->actingAs($user)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('tahun-ajaran.index'))
            ->assertForbidden();

        // Create: Forbidden (403)
        $this->actingAs($user)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('tahun-ajaran.store'), [
                'nama' => '2026/2027 Guru',
                'tanggal_mulai' => '2026-07-15',
                'tanggal_selesai' => '2027-06-20',
            ])
            ->assertForbidden();
    }

    public function test_wali_kelas_has_no_access_to_data_induk_and_receives_403(): void
    {
        $user = $this->createUserWithRole('wali_kelas');

        // View: Forbidden (403)
        $this->actingAs($user)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('rombel.index'))
            ->assertForbidden();

        // Create: Forbidden (403)
        $this->actingAs($user)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('rombel.store'), [
                'nama' => 'X-Wali',
                'tingkat' => 10,
                'kuota' => 36,
            ])
            ->assertForbidden();
    }

    public function test_orang_tua_has_no_access_to_data_induk_and_receives_403(): void
    {
        $user = $this->createUserWithRole('orang_tua');

        // View: Forbidden (403)
        $this->actingAs($user)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('kalender.index'))
            ->assertForbidden();

        // Create: Forbidden (403)
        $this->actingAs($user)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('kalender.hari-libur.store'), [
                'tanggal_mulai' => '2026-12-25',
                'tanggal_selesai' => '2026-12-26',
                'keterangan' => 'Libur',
                'jenis' => 'nasional',
            ])
            ->assertForbidden();
    }
}
