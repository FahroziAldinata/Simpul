<?php

namespace Tests\Feature\Jadwal;

use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Rombel;
use App\Models\Ruang;
use App\Models\Sekolah;
use App\Models\Semester;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class JadwalDatabaseConstraintTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private Semester $semester;

    private Pegawai $guru1;

    private Pegawai $guru2;

    private Ruang $ruang1;

    private Ruang $ruang2;

    private Rombel $rombel1;

    private Rombel $rombel2;

    private MataPelajaran $mapel1;

    private MataPelajaran $mapel2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sekolah = Sekolah::factory()->create();
        $this->semester = Semester::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $this->guru1 = Pegawai::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $this->guru2 = Pegawai::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $this->ruang1 = Ruang::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $this->ruang2 = Ruang::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $this->rombel1 = Rombel::factory()->create(['sekolah_id' => $this->sekolah->id, 'semester_id' => $this->semester->id]);
        $this->rombel2 = Rombel::factory()->create(['sekolah_id' => $this->sekolah->id, 'semester_id' => $this->semester->id]);
        $this->mapel1 = MataPelajaran::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $this->mapel2 = MataPelajaran::factory()->create(['sekolah_id' => $this->sekolah->id]);
    }

    /**
     * T-11.03 (H1): Insert bentrok guru langsung via DB (bypass aplikasi) harus melempar QueryException.
     */
    public function test_insert_bentrok_guru_langsung_via_db_melempar_query_exception(): void
    {
        // Baris 1: Guru 1 mengajar di rombel 1, ruang 1, hari Senin (1), jam 1 s.d. 3 (range [1, 3))
        DB::table('jadwal_pelajaran')->insert([
            'id' => (string) Str::uuid(),
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel1->id,
            'mata_pelajaran_id' => $this->mapel1->id,
            'guru_id' => $this->guru1->id,
            'ruang_id' => $this->ruang1->id,
            'hari' => 1,
            'jam_mulai_ke' => 1,
            'jam_selesai_ke' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Baris 2: Guru 1 yang SAMA dijadwalkan di rombel 2, ruang 2, hari Senin (1), jam 2 s.d. 4 (overlap di jam 2)
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('excl_guru_bentrok');

        DB::table('jadwal_pelajaran')->insert([
            'id' => (string) Str::uuid(),
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel2->id,
            'mata_pelajaran_id' => $this->mapel2->id,
            'guru_id' => $this->guru1->id,
            'ruang_id' => $this->ruang2->id,
            'hari' => 1,
            'jam_mulai_ke' => 2,
            'jam_selesai_ke' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * T-11.03 (H2): Insert bentrok ruang langsung via DB (bypass aplikasi) harus melempar QueryException.
     */
    public function test_insert_bentrok_ruang_langsung_via_db_melempar_query_exception(): void
    {
        // Baris 1: Ruang 1 dipakai guru 1, rombel 1, hari Selasa (2), jam 3 s.d. 5
        DB::table('jadwal_pelajaran')->insert([
            'id' => (string) Str::uuid(),
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel1->id,
            'mata_pelajaran_id' => $this->mapel1->id,
            'guru_id' => $this->guru1->id,
            'ruang_id' => $this->ruang1->id,
            'hari' => 2,
            'jam_mulai_ke' => 3,
            'jam_selesai_ke' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Baris 2: Ruang 1 yang SAMA dipakai guru 2, rombel 2, hari Selasa (2), jam 4 s.d. 6 (overlap di jam 4)
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('excl_ruang_bentrok');

        DB::table('jadwal_pelajaran')->insert([
            'id' => (string) Str::uuid(),
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel2->id,
            'mata_pelajaran_id' => $this->mapel2->id,
            'guru_id' => $this->guru2->id,
            'ruang_id' => $this->ruang1->id,
            'hari' => 2,
            'jam_mulai_ke' => 4,
            'jam_selesai_ke' => 6,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * T-11.03 (H3): Insert bentrok rombel langsung via DB (bypass aplikasi) harus melempar QueryException.
     */
    public function test_insert_bentrok_rombel_langsung_via_db_melempar_query_exception(): void
    {
        // Baris 1: Rombel 1 belajar dengan guru 1, ruang 1, hari Rabu (3), jam 1 s.d. 3
        DB::table('jadwal_pelajaran')->insert([
            'id' => (string) Str::uuid(),
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel1->id,
            'mata_pelajaran_id' => $this->mapel1->id,
            'guru_id' => $this->guru1->id,
            'ruang_id' => $this->ruang1->id,
            'hari' => 3,
            'jam_mulai_ke' => 1,
            'jam_selesai_ke' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Baris 2: Rombel 1 yang SAMA dijadwalkan dengan guru 2, ruang 2, hari Rabu (3), jam 2 s.d. 4 (overlap di jam 2)
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('excl_rombel_bentrok');

        DB::table('jadwal_pelajaran')->insert([
            'id' => (string) Str::uuid(),
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel1->id,
            'mata_pelajaran_id' => $this->mapel2->id,
            'guru_id' => $this->guru2->id,
            'ruang_id' => $this->ruang2->id,
            'hari' => 3,
            'jam_mulai_ke' => 2,
            'jam_selesai_ke' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Soft-deleted record tidak menghalangi slot yang sama dipakai kembali oleh jadwal baru.
     */
    public function test_soft_deleted_record_tidak_menghalangi_slot_yang_sama_dipakai_kembali(): void
    {
        // Jadwal lama yang sudah di-soft-delete (deleted_at terisi)
        DB::table('jadwal_pelajaran')->insert([
            'id' => (string) Str::uuid(),
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel1->id,
            'mata_pelajaran_id' => $this->mapel1->id,
            'guru_id' => $this->guru1->id,
            'ruang_id' => $this->ruang1->id,
            'hari' => 4,
            'jam_mulai_ke' => 1,
            'jam_selesai_ke' => 3,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => now(),
        ]);

        // Jadwal baru aktif dengan parameter yang sama persis di slot yang sama
        DB::table('jadwal_pelajaran')->insert([
            'id' => (string) Str::uuid(),
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel1->id,
            'mata_pelajaran_id' => $this->mapel2->id,
            'guru_id' => $this->guru1->id,
            'ruang_id' => $this->ruang1->id,
            'hari' => 4,
            'jam_mulai_ke' => 1,
            'jam_selesai_ke' => 3,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);

        $this->assertDatabaseCount('jadwal_pelajaran', 2);
    }

    /**
     * Jadwal dengan ruang_id null tidak bentrok antar sesama ruang null pada slot waktu yang sama.
     */
    public function test_jadwal_dengan_ruang_null_tidak_bentrok_antar_sesama_ruang_null(): void
    {
        DB::table('jadwal_pelajaran')->insert([
            'id' => (string) Str::uuid(),
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel1->id,
            'mata_pelajaran_id' => $this->mapel1->id,
            'guru_id' => $this->guru1->id,
            'ruang_id' => null,
            'hari' => 5,
            'jam_mulai_ke' => 1,
            'jam_selesai_ke' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('jadwal_pelajaran')->insert([
            'id' => (string) Str::uuid(),
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel2->id,
            'mata_pelajaran_id' => $this->mapel2->id,
            'guru_id' => $this->guru2->id,
            'ruang_id' => null,
            'hari' => 5,
            'jam_mulai_ke' => 1,
            'jam_selesai_ke' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseCount('jadwal_pelajaran', 2);
    }

    /**
     * Jam yang berurutan persis (tidak overlap karena half-open range [)) tidak bentrok.
     */
    public function test_jam_berurutan_tidak_overlap_dan_tidak_bentrok(): void
    {
        // Guru 1 mengajar jam 1 s.d. 3 [1, 3) di rombel 1
        DB::table('jadwal_pelajaran')->insert([
            'id' => (string) Str::uuid(),
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel1->id,
            'mata_pelajaran_id' => $this->mapel1->id,
            'guru_id' => $this->guru1->id,
            'ruang_id' => $this->ruang1->id,
            'hari' => 1,
            'jam_mulai_ke' => 1,
            'jam_selesai_ke' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Guru 1 lanjut mengajar jam 3 s.d. 5 [3, 5) di rombel 2 (mulai di batas atas jam sebelumnya)
        DB::table('jadwal_pelajaran')->insert([
            'id' => (string) Str::uuid(),
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel2->id,
            'mata_pelajaran_id' => $this->mapel2->id,
            'guru_id' => $this->guru1->id,
            'ruang_id' => $this->ruang2->id,
            'hari' => 1,
            'jam_mulai_ke' => 3,
            'jam_selesai_ke' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseCount('jadwal_pelajaran', 2);
    }
}
