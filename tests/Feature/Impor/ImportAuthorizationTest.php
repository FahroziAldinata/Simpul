<?php

namespace Tests\Feature\Impor;

use App\Models\ImportBatch;
use App\Models\Sekolah;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected Sekolah $sekolah;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->withoutVite();
        $this->sekolah = Sekolah::factory()->create();
        setPermissionsTeamId($this->sekolah->id);
    }

    public function test_roles_other_than_operator_and_super_admin_are_forbidden_from_import_endpoints(): void
    {
        $batch = ImportBatch::factory()->create([
            'sekolah_id' => $this->sekolah->id,
        ]);

        $unauthorizedRoles = ['kepsek', 'waka_kurikulum', 'guru', 'wali_kelas', 'orang_tua'];

        foreach ($unauthorizedRoles as $roleName) {
            $user = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
            $user->assignRole($roleName);

            // GET index
            $this->actingAs($user)
                ->withSession(['sekolah_id' => $this->sekolah->id])
                ->get(route('siswa.impor.index'))
                ->assertForbidden();

            // GET template
            $this->actingAs($user)
                ->withSession(['sekolah_id' => $this->sekolah->id])
                ->get(route('siswa.impor.template'))
                ->assertForbidden();

            // POST upload
            $this->actingAs($user)
                ->withSession(['sekolah_id' => $this->sekolah->id])
                ->post(route('siswa.impor.upload'))
                ->assertForbidden();

            // GET preview
            $this->actingAs($user)
                ->withSession(['sekolah_id' => $this->sekolah->id])
                ->get(route('siswa.impor.preview', $batch->id))
                ->assertForbidden();

            // POST execute
            $this->actingAs($user)
                ->withSession(['sekolah_id' => $this->sekolah->id])
                ->post(route('siswa.impor.execute', $batch->id))
                ->assertForbidden();

            // POST rollback
            $this->actingAs($user)
                ->withSession(['sekolah_id' => $this->sekolah->id])
                ->post(route('siswa.impor.rollback', $batch->id))
                ->assertForbidden();
        }
    }

    public function test_operator_and_super_admin_are_allowed_on_import_endpoints(): void
    {
        $operator = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $operator->assignRole('operator');

        $this->actingAs($operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('siswa.impor.index'))
            ->assertOk();

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $this->actingAs($superAdmin)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('siswa.impor.index'))
            ->assertOk();
    }
}
