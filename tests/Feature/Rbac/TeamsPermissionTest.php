<?php

namespace Tests\Feature\Rbac;

use App\Models\Sekolah;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamsPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_seven_roles_are_properly_seeded(): void
    {
        $expectedRoles = [
            'super_admin',
            'operator',
            'kepsek',
            'waka_kurikulum',
            'guru',
            'wali_kelas',
            'orang_tua',
        ];

        foreach ($expectedRoles as $roleName) {
            $this->assertDatabaseHas('roles', [
                'name' => $roleName,
                'guard_name' => 'web',
            ]);
        }
    }

    public function test_user_permissions_in_sekolah_a_do_not_leak_to_sekolah_b(): void
    {
        $sekolahA = Sekolah::factory()->create();
        $sekolahB = Sekolah::factory()->create();

        $user = User::factory()->create(['sekolah_id' => $sekolahA->id]);

        // Assign operator role in Sekolah A context
        setPermissionsTeamId($sekolahA->id);
        $user->assignRole('operator');

        // Verify user has permission in Sekolah A
        $this->assertTrue($user->hasRole('operator'));
        $this->assertTrue($user->can('pegawai.create'));

        // Switch context to Sekolah B
        setPermissionsTeamId($sekolahB->id);
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');

        // Verify role and permissions do NOT leak into Sekolah B
        $this->assertFalse($user->hasRole('operator'));
        $this->assertFalse($user->can('pegawai.create'));
    }

    public function test_super_admin_bypasses_all_permission_checks(): void
    {
        $sekolah = Sekolah::factory()->create();
        $superAdmin = User::factory()->create(['sekolah_id' => null]);

        setPermissionsTeamId($sekolah->id);
        $superAdmin->assignRole('super_admin');

        $this->assertTrue($superAdmin->can('audit_log.view'));
        $this->assertTrue($superAdmin->can('any.arbitrary.permission'));
    }
}
