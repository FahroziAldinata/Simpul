<?php

namespace Tests\Feature\Auth;

use App\Models\Pegawai;
use App\Models\Sekolah;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SoftDeletedUserSessionTest extends TestCase
{
    use RefreshDatabase;

    protected Sekolah $sekolah;

    protected User $operator;

    protected User $pegawaiUser;

    protected Pegawai $pegawai;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->sekolah = Sekolah::factory()->create();
        setPermissionsTeamId($this->sekolah->id);

        $this->operator = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $this->operator->assignRole('operator');

        $this->pegawaiUser = User::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'email' => 'guru.aktif@simpul.sch.id',
            'password' => bcrypt('Password123!'),
        ]);
        $this->pegawaiUser->assignRole('guru');

        $this->pegawai = Pegawai::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'user_id' => $this->pegawaiUser->id,
            'nama' => 'Guru Aktif',
            'email' => $this->pegawaiUser->email,
        ]);
    }

    public function test_active_sessions_in_database_are_deleted_when_pegawai_and_user_are_soft_deleted(): void
    {
        // 1. Manually insert an active session record for this user in the sessions table
        DB::table('sessions')->insert([
            'id' => 'session_test_token_123',
            'user_id' => $this->pegawaiUser->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit Browser',
            'payload' => base64_encode(serialize(['_token' => 'dummy_token'])),
            'last_activity' => time(),
        ]);

        $this->assertDatabaseHas('sessions', [
            'id' => 'session_test_token_123',
            'user_id' => $this->pegawaiUser->id,
        ]);

        // 2. Operator soft-deletes the employee
        $this->actingAs($this->operator)
            ->delete(route('pegawai.destroy', $this->pegawai->id))
            ->assertRedirect();

        // 3. User and Pegawai are soft-deleted
        $this->assertSoftDeleted('pegawai', ['id' => $this->pegawai->id]);
        $this->assertSoftDeleted('users', ['id' => $this->pegawaiUser->id]);

        // 4. Session rows belonging to this user must be completely removed
        $this->assertDatabaseMissing('sessions', [
            'user_id' => $this->pegawaiUser->id,
        ]);
    }

    public function test_subsequent_request_from_soft_deleted_user_is_rejected_and_redirects_to_login(): void
    {
        // 1. User starts with an active session
        $this->actingAs($this->pegawaiUser);

        // Verify user can access dashboard
        $response = $this->get(route('dashboard'));
        $response->assertOk();

        // 2. Operator soft-deletes the Pegawai and user
        $this->pegawai->delete();
        $this->pegawaiUser->delete();
        $this->assertTrue($this->pegawaiUser->trashed());

        // 3. User attempts the next request with their existing session
        $nextResponse = $this->get(route('dashboard'));

        // Must be redirected to login and authenticated session cleared
        $nextResponse->assertRedirect(route('login'));
        $nextResponse->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_subsequent_request_via_session_cookie_from_soft_deleted_user_is_rejected(): void
    {
        // 1. User logs in via standard login route
        $loginResponse = $this->post(route('login.store'), [
            'email' => $this->pegawaiUser->email,
            'password' => 'Password123!',
        ]);
        $loginResponse->assertRedirect();
        $this->assertAuthenticatedAs($this->pegawaiUser);

        // 2. Pegawai and user are soft-deleted
        $this->pegawai->delete();

        // 3. Forget in-memory auth guards to simulate a separate HTTP request
        $this->app['auth']->forgetGuards();

        // 4. User attempts next request using active session
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_inertia_request_from_soft_deleted_user_receives_inertia_redirect_to_login(): void
    {
        // 1. User starts with an active session
        $this->actingAs($this->pegawaiUser);

        // 2. Soft-delete user
        $this->pegawaiUser->delete();

        // 3. User sends next request with X-Inertia header
        $response = $this->withHeader('X-Inertia', 'true')->get(route('dashboard'));

        // Expect 409 Conflict with X-Inertia-Location header for Inertia full page visit to login
        $response->assertStatus(409);
        $this->assertEquals(route('login'), $response->headers->get('X-Inertia-Location'));
        $this->assertGuest();
    }
}
