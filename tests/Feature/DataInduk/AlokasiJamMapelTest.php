<?php

namespace Tests\Feature\DataInduk;

use App\Models\AlokasiJamMapel;
use App\Models\JamKerja;
use App\Models\MataPelajaran;
use App\Models\Rombel;
use App\Models\Sekolah;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlokasiJamMapelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_operator_can_allocate_subject_hours_to_rombel(): void
    {
        $sekolah = Sekolah::factory()->create();

        $operator = User::factory()->create(['sekolah_id' => $sekolah->id]);
        setPermissionsTeamId($sekolah->id);
        $operator->assignRole('operator');

        $ta = TahunAjaran::factory()->create(['sekolah_id' => $sekolah->id, 'is_aktif' => true]);
        $semester = Semester::factory()->create(['sekolah_id' => $sekolah->id, 'tahun_ajaran_id' => $ta->id, 'is_aktif' => true]);
        $rombel = Rombel::factory()->create(['sekolah_id' => $sekolah->id, 'semester_id' => $semester->id]);
        $mapel = MataPelajaran::factory()->create(['sekolah_id' => $sekolah->id]);

        $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id, 'selected_semester_id' => $semester->id])
            ->get(route('alokasi-jam.index', ['rombel_id' => $rombel->id]))
            ->assertOk();

        $payload = [
            'rombel_id' => $rombel->id,
            'mata_pelajaran_id' => $mapel->id,
            'jam_per_minggu' => 4,
        ];

        $response = $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id, 'selected_semester_id' => $semester->id])
            ->post(route('alokasi-jam.store'), $payload);

        $response->assertRedirect();

        $this->assertDatabaseHas('alokasi_jam_mapel', [
            'sekolah_id' => $sekolah->id,
            'rombel_id' => $rombel->id,
            'mata_pelajaran_id' => $mapel->id,
            'jam_per_minggu' => 4,
        ]);
    }

    public function test_rejects_alokasi_jam_exceeding_total_weekly_slots(): void
    {
        $sekolah = Sekolah::factory()->create();

        $operator = User::factory()->create(['sekolah_id' => $sekolah->id]);
        setPermissionsTeamId($sekolah->id);
        $operator->assignRole('operator');

        $ta = TahunAjaran::factory()->create(['sekolah_id' => $sekolah->id, 'is_aktif' => true]);
        $semester = Semester::factory()->create(['sekolah_id' => $sekolah->id, 'tahun_ajaran_id' => $ta->id, 'is_aktif' => true]);
        $rombel = Rombel::factory()->create(['sekolah_id' => $sekolah->id, 'semester_id' => $semester->id]);

        // Default fallback totalSlot is 40
        $this->assertSame(40, JamKerja::totalSlotMingguan($sekolah->id));

        $mapel1 = MataPelajaran::factory()->create(['sekolah_id' => $sekolah->id]);
        AlokasiJamMapel::factory()->create([
            'sekolah_id' => $sekolah->id,
            'rombel_id' => $rombel->id,
            'mata_pelajaran_id' => $mapel1->id,
            'jam_per_minggu' => 38,
        ]);

        $mapel2 = MataPelajaran::factory()->create(['sekolah_id' => $sekolah->id]);

        // Trying to allocate 4 JP (38 + 4 = 42 > 40)
        $response = $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id, 'selected_semester_id' => $semester->id])
            ->post(route('alokasi-jam.store'), [
                'rombel_id' => $rombel->id,
                'mata_pelajaran_id' => $mapel2->id,
                'jam_per_minggu' => 4,
            ]);

        $response->assertSessionHasErrors('jam_per_minggu');
    }

    public function test_rejects_duplicate_subject_allocation_on_same_rombel(): void
    {
        $sekolah = Sekolah::factory()->create();

        $operator = User::factory()->create(['sekolah_id' => $sekolah->id]);
        setPermissionsTeamId($sekolah->id);
        $operator->assignRole('operator');

        $ta = TahunAjaran::factory()->create(['sekolah_id' => $sekolah->id, 'is_aktif' => true]);
        $semester = Semester::factory()->create(['sekolah_id' => $sekolah->id, 'tahun_ajaran_id' => $ta->id, 'is_aktif' => true]);
        $rombel = Rombel::factory()->create(['sekolah_id' => $sekolah->id, 'semester_id' => $semester->id]);
        $mapel = MataPelajaran::factory()->create(['sekolah_id' => $sekolah->id]);

        AlokasiJamMapel::factory()->create([
            'sekolah_id' => $sekolah->id,
            'rombel_id' => $rombel->id,
            'mata_pelajaran_id' => $mapel->id,
            'jam_per_minggu' => 2,
        ]);

        $response = $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id, 'selected_semester_id' => $semester->id])
            ->post(route('alokasi-jam.store'), [
                'rombel_id' => $rombel->id,
                'mata_pelajaran_id' => $mapel->id,
                'jam_per_minggu' => 3,
            ]);

        $response->assertSessionHasErrors('mata_pelajaran_id');
    }

    public function test_tenant_isolation_404_when_deleting_other_school_alokasi(): void
    {
        $sekolahA = Sekolah::factory()->create();
        $sekolahB = Sekolah::factory()->create();

        $operatorA = User::factory()->create(['sekolah_id' => $sekolahA->id]);
        setPermissionsTeamId($sekolahA->id);
        $operatorA->assignRole('operator');

        $alokasiB = AlokasiJamMapel::factory()->create([
            'sekolah_id' => $sekolahB->id,
            'jam_per_minggu' => 2,
        ]);

        $this->actingAs($operatorA)
            ->withSession(['sekolah_id' => $sekolahA->id])
            ->delete(route('alokasi-jam.destroy', $alokasiB->id))
            ->assertNotFound();

        $this->assertDatabaseHas('alokasi_jam_mapel', ['id' => $alokasiB->id]);
    }
}
