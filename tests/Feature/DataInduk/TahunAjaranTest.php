<?php

namespace Tests\Feature\DataInduk;

use App\Models\Sekolah;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TahunAjaranTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_operator_can_view_tahun_ajaran_list(): void
    {
        $sekolah = Sekolah::factory()->create();

        $operator = User::factory()->create(['sekolah_id' => $sekolah->id]);
        setPermissionsTeamId($sekolah->id);
        $operator->assignRole('operator');

        $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->get(route('tahun-ajaran.index'))
            ->assertOk();
    }

    public function test_operator_can_create_tahun_ajaran_and_auto_generates_two_semesters(): void
    {
        $sekolah = Sekolah::factory()->create();

        $operator = User::factory()->create(['sekolah_id' => $sekolah->id]);
        setPermissionsTeamId($sekolah->id);
        $operator->assignRole('operator');

        $payload = [
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-15',
            'tanggal_selesai' => '2027-06-20',
            'is_aktif' => true,
        ];

        $response = $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->post(route('tahun-ajaran.store'), $payload);

        $response->assertRedirect();

        $ta = TahunAjaran::where('sekolah_id', $sekolah->id)->firstOrFail();
        $this->assertSame('2026/2027', $ta->nama);
        $this->assertTrue($ta->is_aktif);

        // Memastikan 2 semester (Ganjil & Genap) otomatis terbuat
        $semesters = Semester::where('tahun_ajaran_id', $ta->id)->orderBy('nama')->get();
        $this->assertCount(2, $semesters);
        $this->assertSame('Ganjil', $semesters[0]->nama);
        $this->assertTrue($semesters[0]->is_aktif);
        $this->assertSame('Genap', $semesters[1]->nama);
        $this->assertFalse($semesters[1]->is_aktif);
    }

    public function test_activating_new_tahun_ajaran_deactivates_previous_active_one(): void
    {
        $sekolah = Sekolah::factory()->create();

        $operator = User::factory()->create(['sekolah_id' => $sekolah->id]);
        setPermissionsTeamId($sekolah->id);
        $operator->assignRole('operator');

        $ta1 = TahunAjaran::factory()->create([
            'sekolah_id' => $sekolah->id,
            'nama' => '2025/2026',
            'is_aktif' => true,
        ]);

        $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->post(route('tahun-ajaran.store'), [
                'nama' => '2026/2027',
                'tanggal_mulai' => '2026-07-15',
                'tanggal_selesai' => '2027-06-20',
                'is_aktif' => true,
            ]);

        $ta1->refresh();
        $this->assertFalse($ta1->is_aktif);

        $ta2 = TahunAjaran::where('sekolah_id', $sekolah->id)->where('nama', '2026/2027')->firstOrFail();
        $this->assertTrue($ta2->is_aktif);
    }

    public function test_database_partial_unique_index_rejects_duplicate_active_tahun_ajaran(): void
    {
        $sekolah = Sekolah::factory()->create();

        TahunAjaran::factory()->create([
            'sekolah_id' => $sekolah->id,
            'is_aktif' => true,
        ]);

        $this->expectException(QueryException::class);

        // Force insert duplicate active via raw query bypassing app logic to test DB partial index
        DB::table('tahun_ajaran')->insert([
            'id' => (string) Str::uuid(),
            'sekolah_id' => $sekolah->id,
            'nama' => '2027/2028',
            'tanggal_mulai' => '2027-07-15',
            'tanggal_selesai' => '2028-06-20',
            'is_aktif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_operator_can_activate_semester(): void
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
            ->post(route('semester.activate', $semGenap->id));

        $response->assertRedirect();

        $semGanjil->refresh();
        $semGenap->refresh();

        $this->assertFalse($semGanjil->is_aktif);
        $this->assertTrue($semGenap->is_aktif);
    }

    public function test_operator_cannot_delete_tahun_ajaran(): void
    {
        $sekolah = Sekolah::factory()->create();

        $operator = User::factory()->create(['sekolah_id' => $sekolah->id]);
        setPermissionsTeamId($sekolah->id);
        $operator->assignRole('operator');

        $ta = TahunAjaran::factory()->create([
            'sekolah_id' => $sekolah->id,
            'is_aktif' => false,
        ]);

        $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->delete(route('tahun-ajaran.destroy', $ta->id))
            ->assertForbidden();

        $this->assertDatabaseHas('tahun_ajaran', ['id' => $ta->id]);
    }

    public function test_cannot_delete_active_tahun_ajaran(): void
    {
        $sekolah = Sekolah::factory()->create();

        setPermissionsTeamId($sekolah->id);
        $superAdmin = User::factory()->create(['sekolah_id' => null]);
        $superAdmin->assignRole('super_admin');

        $ta = TahunAjaran::factory()->create([
            'sekolah_id' => $sekolah->id,
            'is_aktif' => true,
        ]);

        $response = $this->actingAs($superAdmin)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->delete(route('tahun-ajaran.destroy', $ta->id));

        $response->assertRedirect();
        $this->assertDatabaseHas('tahun_ajaran', ['id' => $ta->id]);
    }

    public function test_tenant_isolation_404_on_cross_school_access(): void
    {
        $sekolahA = Sekolah::factory()->create();
        $sekolahB = Sekolah::factory()->create();

        setPermissionsTeamId($sekolahA->id);
        $superAdmin = User::factory()->create(['sekolah_id' => null]);
        $superAdmin->assignRole('super_admin');

        $taB = TahunAjaran::factory()->create([
            'sekolah_id' => $sekolahB->id,
            'is_aktif' => false,
        ]);

        $this->actingAs($superAdmin)
            ->withSession(['sekolah_id' => $sekolahA->id])
            ->delete(route('tahun-ajaran.destroy', $taB->id))
            ->assertNotFound();

        $this->assertDatabaseHas('tahun_ajaran', ['id' => $taB->id]);
    }
}
