<?php

namespace Tests\Feature\Layout;

use App\Models\Sekolah;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SidebarRoleMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_super_admin_receives_full_access_and_school_list(): void
    {
        $sekolahA = Sekolah::factory()->create(['nama' => 'SMA 1', 'npsn' => '10000001']);
        $sekolahB = Sekolah::factory()->create(['nama' => 'SMA 2', 'npsn' => '10000002']);

        $user = User::factory()->create(['sekolah_id' => null]);
        setPermissionsTeamId($sekolahA->id);
        $user->assignRole('super_admin');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('auth.roles', ['super_admin'])
                ->has('auth.daftar_sekolah', 2)
                ->where('auth.sekolah_aktif_id', $sekolahA->id)
            );
    }

    public function test_kepsek_receives_audit_log_permission_but_no_school_switcher(): void
    {
        $sekolah = Sekolah::factory()->create();
        $user = User::factory()->create(['sekolah_id' => $sekolah->id]);

        setPermissionsTeamId($sekolah->id);
        $user->assignRole('kepsek');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('auth.roles', ['kepsek'])
                ->has('auth.daftar_sekolah', 0)
                ->where('auth.sekolah_aktif_id', $sekolah->id)
                ->where('auth.permissions', fn ($perms) => in_array('audit_log.view', $perms->toArray()) && in_array('pegawai.view', $perms->toArray()))
            );
    }

    public function test_operator_does_not_have_audit_log_permission(): void
    {
        $sekolah = Sekolah::factory()->create();
        $user = User::factory()->create(['sekolah_id' => $sekolah->id]);

        setPermissionsTeamId($sekolah->id);
        $user->assignRole('operator');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('auth.roles', ['operator'])
                ->where('auth.permissions', fn ($perms) => ! in_array('audit_log.view', $perms->toArray()) && in_array('pegawai.view', $perms->toArray()))
            );
    }

    public function test_guru_receives_teaching_permissions_only(): void
    {
        $sekolah = Sekolah::factory()->create();
        $user = User::factory()->create(['sekolah_id' => $sekolah->id]);

        setPermissionsTeamId($sekolah->id);
        $user->assignRole('guru');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('auth.roles', ['guru'])
                ->where('auth.permissions', fn ($perms) => ! in_array('audit_log.view', $perms->toArray()) && in_array('jadwal.view', $perms->toArray()) && in_array('pegawai.view', $perms->toArray()))
            );
    }

    public function test_orang_tua_has_restricted_permissions_no_pegawai_or_audit(): void
    {
        $sekolah = Sekolah::factory()->create();
        $user = User::factory()->create(['sekolah_id' => $sekolah->id]);

        setPermissionsTeamId($sekolah->id);
        $user->assignRole('orang_tua');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('auth.roles', ['orang_tua'])
                ->where('auth.permissions', fn ($perms) => ! in_array('audit_log.view', $perms->toArray()) && ! in_array('pegawai.view', $perms->toArray()) && ! in_array('absensi.view', $perms->toArray()) && in_array('siswa.view', $perms->toArray()))
            );
    }
}
