<?php

namespace Tests\Feature\DataInduk;

use App\Models\Sekolah;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriodeAktifTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_user_can_switch_selected_semester_period(): void
    {
        $sekolah = Sekolah::factory()->create();

        $operator = User::factory()->create(['sekolah_id' => $sekolah->id]);
        setPermissionsTeamId($sekolah->id);
        $operator->assignRole('operator');

        $ta = TahunAjaran::factory()->create([
            'sekolah_id' => $sekolah->id,
            'is_aktif' => true,
        ]);

        $semGanjil = Semester::factory()->create([
            'sekolah_id' => $sekolah->id,
            'tahun_ajaran_id' => $ta->id,
            'nama' => 'Ganjil',
            'is_aktif' => true,
        ]);

        $semGenap = Semester::factory()->create([
            'sekolah_id' => $sekolah->id,
            'tahun_ajaran_id' => $ta->id,
            'nama' => 'Genap',
            'is_aktif' => false,
        ]);

        $response = $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->post(route('periode-aktif.update'), [
                'semester_id' => $semGenap->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('selected_semester_id', $semGenap->id);
    }

    public function test_tenant_isolation_404_when_switching_to_other_school_semester(): void
    {
        $sekolahA = Sekolah::factory()->create();
        $sekolahB = Sekolah::factory()->create();

        $operatorA = User::factory()->create(['sekolah_id' => $sekolahA->id]);
        setPermissionsTeamId($sekolahA->id);
        $operatorA->assignRole('operator');

        $semB = Semester::factory()->create([
            'sekolah_id' => $sekolahB->id,
        ]);

        $this->actingAs($operatorA)
            ->withSession(['sekolah_id' => $sekolahA->id])
            ->post(route('periode-aktif.update'), [
                'semester_id' => $semB->id,
            ])
            ->assertNotFound();
    }
}
