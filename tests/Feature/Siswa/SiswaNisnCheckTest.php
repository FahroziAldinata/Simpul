<?php

namespace Tests\Feature\Siswa;

use App\Models\Sekolah;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiswaNisnCheckTest extends TestCase
{
    use RefreshDatabase;

    protected Sekolah $sekolah;

    protected User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->sekolah = Sekolah::factory()->create();
        setPermissionsTeamId($this->sekolah->id);

        $this->operator = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $this->operator->assignRole('operator');
    }

    public function test_operator_can_check_nisn_availability(): void
    {
        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->postJson(route('siswa.check-nisn'), ['nisn' => '0012345678']);

        $response->assertOk()
            ->assertJson(['available' => true]);
    }

    public function test_nisn_check_returns_false_if_used_in_other_school(): void
    {
        $sekolahB = Sekolah::factory()->create();
        Siswa::factory()->create([
            'sekolah_id' => $sekolahB->id,
            'nisn' => '0098765432',
        ]);

        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->postJson(route('siswa.check-nisn'), ['nisn' => '0098765432']);

        $response->assertOk()
            ->assertJson([
                'available' => false,
                'message' => 'NISN sudah terdaftar di sistem.',
            ]);
    }

    public function test_nisn_check_returns_true_if_soft_deleted(): void
    {
        $siswa = Siswa::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nisn' => '0077889900',
        ]);
        $siswa->delete();

        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->postJson(route('siswa.check-nisn'), ['nisn' => '0077889900']);

        $response->assertOk()
            ->assertJson(['available' => true]);
    }

    public function test_guru_and_orang_tua_cannot_access_check_nisn(): void
    {
        foreach (['guru', 'orang_tua'] as $role) {
            $user = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
            $user->assignRole($role);

            $this->actingAs($user)
                ->withSession(['sekolah_id' => $this->sekolah->id])
                ->postJson(route('siswa.check-nisn'), ['nisn' => '0012345678'])
                ->assertForbidden();
        }
    }
}
