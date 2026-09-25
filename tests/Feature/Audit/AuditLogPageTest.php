<?php

namespace Tests\Feature\Audit;

use App\Models\Sekolah;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_super_admin_can_access_audit_log_page(): void
    {
        $sekolah = Sekolah::factory()->create();
        $user = User::factory()->create(['sekolah_id' => null]);

        setPermissionsTeamId($sekolah->id);
        $user->assignRole('super_admin');

        $response = $this->actingAs($user)->get(route('audit-logs.index'));

        $response->assertOk();
    }

    public function test_kepsek_can_access_audit_log_page(): void
    {
        $sekolah = Sekolah::factory()->create();
        $user = User::factory()->create(['sekolah_id' => $sekolah->id]);

        setPermissionsTeamId($sekolah->id);
        $user->assignRole('kepsek');

        $response = $this->actingAs($user)->get(route('audit-logs.index'));

        $response->assertOk();
    }

    public function test_guru_cannot_access_audit_log_page(): void
    {
        $sekolah = Sekolah::factory()->create();
        $user = User::factory()->create(['sekolah_id' => $sekolah->id]);

        setPermissionsTeamId($sekolah->id);
        $user->assignRole('guru');

        $response = $this->actingAs($user)->get(route('audit-logs.index'));

        $response->assertForbidden();
    }

    public function test_operator_cannot_access_audit_log_page(): void
    {
        $sekolah = Sekolah::factory()->create();
        $user = User::factory()->create(['sekolah_id' => $sekolah->id]);

        setPermissionsTeamId($sekolah->id);
        $user->assignRole('operator');

        $response = $this->actingAs($user)->get(route('audit-logs.index'));

        $response->assertForbidden();
    }

    public function test_orang_tua_cannot_access_audit_log_page(): void
    {
        $sekolah = Sekolah::factory()->create();
        $user = User::factory()->create(['sekolah_id' => $sekolah->id]);

        setPermissionsTeamId($sekolah->id);
        $user->assignRole('orang_tua');

        $response = $this->actingAs($user)->get(route('audit-logs.index'));

        $response->assertForbidden();
    }
}
