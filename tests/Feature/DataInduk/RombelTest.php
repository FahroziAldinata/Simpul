<?php

namespace Tests\Feature\DataInduk;

use App\Enums\StatusSiswa;
use App\Models\AnggotaRombel;
use App\Models\Pegawai;
use App\Models\Rombel;
use App\Models\Sekolah;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RombelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_operator_can_view_and_create_rombel(): void
    {
        $sekolah = Sekolah::factory()->create();

        $operator = User::factory()->create(['sekolah_id' => $sekolah->id]);
        setPermissionsTeamId($sekolah->id);
        $operator->assignRole('operator');

        $ta = TahunAjaran::factory()->create(['sekolah_id' => $sekolah->id, 'is_aktif' => true]);
        $semester = Semester::factory()->create(['sekolah_id' => $sekolah->id, 'tahun_ajaran_id' => $ta->id, 'is_aktif' => true]);

        $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id, 'selected_semester_id' => $semester->id])
            ->get(route('rombel.index'))
            ->assertOk();

        $payload = [
            'nama' => 'X RPL 1',
            'tingkat' => 10,
            'kuota' => 36,
            'is_aktif' => true,
        ];

        $response = $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id, 'selected_semester_id' => $semester->id])
            ->post(route('rombel.store'), $payload);

        $response->assertRedirect();

        $this->assertDatabaseHas('rombel', [
            'sekolah_id' => $sekolah->id,
            'semester_id' => $semester->id,
            'nama' => 'X RPL 1',
            'tingkat' => 10,
        ]);
    }

    public function test_rejects_duplicate_nama_in_same_semester(): void
    {
        $sekolah = Sekolah::factory()->create();

        $operator = User::factory()->create(['sekolah_id' => $sekolah->id]);
        setPermissionsTeamId($sekolah->id);
        $operator->assignRole('operator');

        $ta = TahunAjaran::factory()->create(['sekolah_id' => $sekolah->id, 'is_aktif' => true]);
        $semester = Semester::factory()->create(['sekolah_id' => $sekolah->id, 'tahun_ajaran_id' => $ta->id, 'is_aktif' => true]);

        Rombel::factory()->create([
            'sekolah_id' => $sekolah->id,
            'semester_id' => $semester->id,
            'nama' => 'X RPL 1',
        ]);

        $response = $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id, 'selected_semester_id' => $semester->id])
            ->post(route('rombel.store'), [
                'nama' => 'X RPL 1',
                'tingkat' => 10,
                'kuota' => 36,
            ]);

        $response->assertSessionHasErrors('nama');
    }

    public function test_rejects_duplicate_wali_kelas_in_same_semester(): void
    {
        $sekolah = Sekolah::factory()->create();

        $operator = User::factory()->create(['sekolah_id' => $sekolah->id]);
        setPermissionsTeamId($sekolah->id);
        $operator->assignRole('operator');

        $ta = TahunAjaran::factory()->create(['sekolah_id' => $sekolah->id, 'is_aktif' => true]);
        $semester = Semester::factory()->create(['sekolah_id' => $sekolah->id, 'tahun_ajaran_id' => $ta->id, 'is_aktif' => true]);

        $guru = Pegawai::factory()->create([
            'sekolah_id' => $sekolah->id,
            'jenis' => 'guru',
        ]);

        Rombel::factory()->create([
            'sekolah_id' => $sekolah->id,
            'semester_id' => $semester->id,
            'nama' => 'X RPL 1',
            'wali_kelas_id' => $guru->id,
        ]);

        $response = $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id, 'selected_semester_id' => $semester->id])
            ->post(route('rombel.store'), [
                'nama' => 'X RPL 2',
                'tingkat' => 10,
                'kuota' => 36,
                'wali_kelas_id' => $guru->id,
            ]);

        $response->assertSessionHasErrors('wali_kelas_id');
    }

    public function test_operator_cannot_delete_rombel(): void
    {
        $sekolah = Sekolah::factory()->create();

        $operator = User::factory()->create(['sekolah_id' => $sekolah->id]);
        setPermissionsTeamId($sekolah->id);
        $operator->assignRole('operator');

        $rombel = Rombel::factory()->create(['sekolah_id' => $sekolah->id]);

        $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->delete(route('rombel.destroy', $rombel->id))
            ->assertForbidden();

        $this->assertDatabaseHas('rombel', ['id' => $rombel->id]);
    }

    public function test_super_admin_can_delete_rombel(): void
    {
        $sekolah = Sekolah::factory()->create();

        setPermissionsTeamId($sekolah->id);
        $superAdmin = User::factory()->create(['sekolah_id' => null]);
        $superAdmin->assignRole('super_admin');

        $rombel = Rombel::factory()->create(['sekolah_id' => $sekolah->id]);

        $this->actingAs($superAdmin)
            ->withSession(['sekolah_id' => $sekolah->id])
            ->delete(route('rombel.destroy', $rombel->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('rombel', ['id' => $rombel->id]);
    }

    public function test_tenant_isolation_404_when_deleting_other_school_rombel(): void
    {
        $sekolahA = Sekolah::factory()->create();
        $sekolahB = Sekolah::factory()->create();

        setPermissionsTeamId($sekolahA->id);
        $superAdmin = User::factory()->create(['sekolah_id' => null]);
        $superAdmin->assignRole('super_admin');

        $rombelB = Rombel::factory()->create([
            'sekolah_id' => $sekolahB->id,
            'nama' => 'VII-A',
        ]);

        $this->actingAs($superAdmin)
            ->withSession(['sekolah_id' => $sekolahA->id])
            ->delete(route('rombel.destroy', $rombelB->id))
            ->assertNotFound();

        $this->assertDatabaseHas('rombel', ['id' => $rombelB->id]);
    }

    public function test_rombel_counts_only_active_students_and_excludes_graduated_transferred_and_soft_deleted(): void
    {
        $sekolah = Sekolah::factory()->create();

        $operator = User::factory()->create(['sekolah_id' => $sekolah->id]);
        setPermissionsTeamId($sekolah->id);
        $operator->assignRole('operator');

        $ta = TahunAjaran::factory()->create(['sekolah_id' => $sekolah->id, 'is_aktif' => true]);
        $semester = Semester::factory()->create(['sekolah_id' => $sekolah->id, 'tahun_ajaran_id' => $ta->id, 'is_aktif' => true]);

        $rombel = Rombel::factory()->create([
            'sekolah_id' => $sekolah->id,
            'semester_id' => $semester->id,
            'nama' => 'X RPL 1',
            'kuota' => 2,
        ]);

        // 2 Active students
        $siswaAktif1 = Siswa::factory()->create(['sekolah_id' => $sekolah->id, 'status' => StatusSiswa::Aktif]);
        $siswaAktif2 = Siswa::factory()->create(['sekolah_id' => $sekolah->id, 'status' => StatusSiswa::Aktif]);

        // 1 Lulus student
        $siswaLulus = Siswa::factory()->create(['sekolah_id' => $sekolah->id, 'status' => StatusSiswa::Lulus]);

        // 1 Pindah student
        $siswaPindah = Siswa::factory()->create(['sekolah_id' => $sekolah->id, 'status' => StatusSiswa::Pindah]);

        // 1 Soft-deleted student (even if status was aktif)
        $siswaDeleted = Siswa::factory()->create(['sekolah_id' => $sekolah->id, 'status' => StatusSiswa::Aktif]);
        $siswaDeleted->delete();

        foreach ([$siswaAktif1, $siswaAktif2, $siswaLulus, $siswaPindah, $siswaDeleted] as $idx => $s) {
            AnggotaRombel::create([
                'sekolah_id' => $sekolah->id,
                'rombel_id' => $rombel->id,
                'siswa_id' => $s->id,
                'semester_id' => $semester->id,
                'nomor_absen' => $idx + 1,
            ]);
        }

        $response = $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id, 'selected_semester_id' => $semester->id])
            ->get(route('rombel.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('data-induk/rombel/Index')
            ->has('rombel', 1)
            ->where('rombel.0.jumlah_siswa', 2)
        );
    }

    public function test_rombel_query_count_remains_constant_regardless_of_rombel_count(): void
    {
        $sekolah = Sekolah::factory()->create();

        $operator = User::factory()->create(['sekolah_id' => $sekolah->id]);
        setPermissionsTeamId($sekolah->id);
        $operator->assignRole('operator');

        $ta = TahunAjaran::factory()->create(['sekolah_id' => $sekolah->id, 'is_aktif' => true]);
        $semester = Semester::factory()->create(['sekolah_id' => $sekolah->id, 'tahun_ajaran_id' => $ta->id, 'is_aktif' => true]);

        // Warm up permissions and session
        $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id, 'selected_semester_id' => $semester->id])
            ->get(route('rombel.index'));

        // Case 1: 1 Rombel with students
        $rombel1 = Rombel::factory()->create([
            'sekolah_id' => $sekolah->id,
            'semester_id' => $semester->id,
        ]);
        $siswa1 = Siswa::factory()->create(['sekolah_id' => $sekolah->id, 'status' => StatusSiswa::Aktif]);
        AnggotaRombel::create([
            'sekolah_id' => $sekolah->id,
            'rombel_id' => $rombel1->id,
            'siswa_id' => $siswa1->id,
            'semester_id' => $semester->id,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id, 'selected_semester_id' => $semester->id])
            ->get(route('rombel.index'));

        $queryCountWith1 = count(DB::getQueryLog());

        // Case 2: Add 3 more Rombels with students
        for ($i = 2; $i <= 4; $i++) {
            $r = Rombel::factory()->create([
                'sekolah_id' => $sekolah->id,
                'semester_id' => $semester->id,
            ]);
            $s = Siswa::factory()->create(['sekolah_id' => $sekolah->id, 'status' => StatusSiswa::Aktif]);
            AnggotaRombel::create([
                'sekolah_id' => $sekolah->id,
                'rombel_id' => $r->id,
                'siswa_id' => $s->id,
                'semester_id' => $semester->id,
            ]);
        }

        DB::flushQueryLog();

        $this->actingAs($operator)
            ->withSession(['sekolah_id' => $sekolah->id, 'selected_semester_id' => $semester->id])
            ->get(route('rombel.index'));

        $queryCountWith4 = count(DB::getQueryLog());

        $this->assertSame($queryCountWith1, $queryCountWith4, "Expected constant query count (no N+1), got {$queryCountWith1} with 1 rombel and {$queryCountWith4} with 4 rombels.");
    }
}
