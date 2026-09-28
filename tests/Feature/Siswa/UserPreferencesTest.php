<?php

namespace Tests\Feature\Siswa;

use App\Models\Sekolah;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class UserPreferencesTest extends TestCase
{
    use RefreshDatabase;

    protected Sekolah $sekolah;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->sekolah = Sekolah::factory()->create();
        setPermissionsTeamId($this->sekolah->id);

        $this->user = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $this->user->assignRole('operator');
    }

    public function test_user_can_update_table_column_preferences(): void
    {
        $payload = [
            'preferences' => [
                'siswa_columns' => ['nisn', 'nama', 'status', 'no_hp'],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->patchJson(route('user.preferences.update'), $payload);

        $response->assertOk()
            ->assertJsonPath('preferences.siswa_columns', ['nisn', 'nama', 'status', 'no_hp']);

        $this->user->refresh();
        $this->assertSame(['nisn', 'nama', 'status', 'no_hp'], $this->user->preferences['siswa_columns']);
    }

    public function test_updating_preferences_is_excluded_from_activity_log(): void
    {
        Activity::query()->delete();

        $this->actingAs($this->user)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->patchJson(route('user.preferences.update'), [
                'preferences' => ['siswa_columns' => ['nisn', 'nama']],
            ]);

        // Tidak boleh ada activity log yang mencatat perubahan preferences saja
        $count = Activity::where('subject_type', User::class)
            ->where('subject_id', (string) $this->user->id)
            ->count();

        $this->assertSame(0, $count);
    }
}
