<?php

namespace Tests\Feature\Tenancy;

use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetSekolahAktifTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_user_automatically_has_sekolah_id_set_in_session(): void
    {
        $sekolah = Sekolah::factory()->create();
        $user = User::factory()->create([
            'sekolah_id' => $sekolah->id,
        ]);

        $this->actingAs($user)->get(route('dashboard'));

        $this->assertEquals($sekolah->id, session('sekolah_id'));
    }

    public function test_super_admin_gets_deterministic_first_school_by_npsn(): void
    {
        $sekolah2 = Sekolah::factory()->create(['npsn' => '20000000', 'nama' => 'Sekolah Dua']);
        $sekolah1 = Sekolah::factory()->create(['npsn' => '10000000', 'nama' => 'Sekolah Satu']);

        $superAdmin = User::factory()->create([
            'sekolah_id' => null,
        ]);

        $this->actingAs($superAdmin)->get(route('dashboard'));

        $this->assertEquals($sekolah1->id, session('sekolah_id'));
    }

    public function test_super_admin_can_switch_active_school(): void
    {
        $sekolah1 = Sekolah::factory()->create(['npsn' => '10000000']);
        $sekolah2 = Sekolah::factory()->create(['npsn' => '20000000']);

        $superAdmin = User::factory()->create([
            'sekolah_id' => null,
        ]);

        $response = $this->actingAs($superAdmin)->post(route('sekolah-aktif.update'), [
            'sekolah_id' => $sekolah2->id,
        ]);

        $response->assertRedirect();
        $this->assertEquals($sekolah2->id, session('sekolah_id'));
    }

    public function test_regular_user_cannot_switch_active_school(): void
    {
        $sekolah1 = Sekolah::factory()->create(['npsn' => '10000000']);
        $sekolah2 = Sekolah::factory()->create(['npsn' => '20000000']);

        $regularUser = User::factory()->create([
            'sekolah_id' => $sekolah1->id,
        ]);

        $response = $this->actingAs($regularUser)->post(route('sekolah-aktif.update'), [
            'sekolah_id' => $sekolah2->id,
        ]);

        $response->assertForbidden();
    }
}
