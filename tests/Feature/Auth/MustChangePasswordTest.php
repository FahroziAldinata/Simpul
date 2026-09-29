<?php

namespace Tests\Feature\Auth;

use App\Models\Sekolah;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MustChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    protected Sekolah $sekolah;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->sekolah = Sekolah::factory()->create();
        setPermissionsTeamId($this->sekolah->id);
    }

    public function test_user_with_must_change_password_flag_is_redirected_to_security_settings(): void
    {
        $user = User::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'must_change_password' => true,
        ]);
        $user->assignRole('operator');

        // Accessing dashboard via regular HTTP request is redirected to security.edit
        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertRedirect(route('security.edit'));

        // Accessing another route via Inertia request is redirected to security.edit
        $responseInertia = $this->actingAs($user)
            ->withHeader('X-Inertia', 'true')
            ->get(route('siswa.index'));

        if ($responseInertia->getStatusCode() === 409) {
            $responseInertia->assertHeader('X-Inertia-Location', route('security.edit'));
        } else {
            $responseInertia->assertRedirect(route('security.edit'));
        }
    }

    public function test_user_with_must_change_password_can_access_security_page_and_logout(): void
    {
        $user = User::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'must_change_password' => true,
        ]);
        $user->assignRole('operator');

        // Can access security edit page without being redirected in loop
        $response = $this->actingAs($user)->get(route('security.edit'));
        $response->assertStatus(200);

        // Can perform logout
        $responseLogout = $this->actingAs($user)->post(route('logout'));
        $responseLogout->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_new_password_cannot_be_same_as_current_password(): void
    {
        $initialPassword = 'InitialSecretPassword123#';
        $user = User::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'password' => Hash::make($initialPassword),
            'must_change_password' => true,
        ]);

        $response = $this->actingAs($user)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => $initialPassword,
                'password' => $initialPassword,
                'password_confirmation' => $initialPassword,
            ]);

        $response->assertSessionHasErrors(['password']);
        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_must_change_password_flag_is_cleared_after_successful_password_update(): void
    {
        $initialPassword = 'InitialSecretPassword123#';
        $newPassword = 'NewSecretPassword456#';

        $user = User::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'password' => Hash::make($initialPassword),
            'must_change_password' => true,
        ]);
        $user->assignRole('operator');

        $response = $this->actingAs($user)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), [
                'current_password' => $initialPassword,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('security.edit'));

        $freshUser = $user->fresh();
        $this->assertFalse($freshUser->must_change_password);
        $this->assertTrue(Hash::check($newPassword, $freshUser->password));

        // After password is changed, user can access dashboard normally
        $dashboardResponse = $this->actingAs($freshUser)->get(route('dashboard'));
        $dashboardResponse->assertStatus(200);
    }
}
