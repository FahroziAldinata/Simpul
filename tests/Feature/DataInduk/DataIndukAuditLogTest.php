<?php

namespace Tests\Feature\DataInduk;

use App\Models\AlokasiJamMapel;
use App\Models\HariLibur;
use App\Models\JamKerja;
use App\Models\Jurusan;
use App\Models\MataPelajaran;
use App\Models\Rombel;
use App\Models\Ruang;
use App\Models\Sekolah;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataIndukAuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_audit_log_records_changes_on_all_data_induk_models(): void
    {
        $sekolah = Sekolah::factory()->create();

        $operator = User::factory()->create(['sekolah_id' => $sekolah->id]);
        setPermissionsTeamId($sekolah->id);
        $operator->assignRole('operator');

        $this->actingAs($operator);

        // 1. TahunAjaran & Semester
        $ta = TahunAjaran::factory()->create([
            'sekolah_id' => $sekolah->id,
            'nama' => '2026/2027',
        ]);
        $ta->update(['nama' => '2026/2027 Revisi']);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => TahunAjaran::class,
            'subject_id' => $ta->id,
            'event' => 'updated',
        ]);

        $semester = Semester::factory()->create([
            'sekolah_id' => $sekolah->id,
            'tahun_ajaran_id' => $ta->id,
            'nama' => 'Ganjil',
        ]);
        $semester->update(['nama' => 'Genap']);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Semester::class,
            'subject_id' => $semester->id,
            'event' => 'updated',
        ]);

        // 2. Jurusan
        $jurusan = Jurusan::factory()->create([
            'sekolah_id' => $sekolah->id,
            'nama' => 'Teknik Komputer',
        ]);
        $jurusan->update(['nama' => 'Teknik Komputer & Jaringan']);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Jurusan::class,
            'subject_id' => $jurusan->id,
            'event' => 'updated',
        ]);

        // 3. Ruang
        $ruang = Ruang::factory()->create([
            'sekolah_id' => $sekolah->id,
            'nama' => 'Lab 1',
        ]);
        $ruang->update(['nama' => 'Laboratorium Komputer 1']);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Ruang::class,
            'subject_id' => $ruang->id,
            'event' => 'updated',
        ]);

        // 4. MataPelajaran
        $mapel = MataPelajaran::factory()->create([
            'sekolah_id' => $sekolah->id,
            'nama' => 'Matematika Dasar',
        ]);
        $mapel->update(['nama' => 'Matematika Lanjut']);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => MataPelajaran::class,
            'subject_id' => $mapel->id,
            'event' => 'updated',
        ]);

        // 5. Rombel
        $rombel = Rombel::factory()->create([
            'sekolah_id' => $sekolah->id,
            'semester_id' => $semester->id,
            'nama' => 'X-A',
        ]);
        $rombel->update(['nama' => 'X-RPL-1']);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Rombel::class,
            'subject_id' => $rombel->id,
            'event' => 'updated',
        ]);

        // 6. AlokasiJamMapel
        $alokasi = AlokasiJamMapel::factory()->create([
            'sekolah_id' => $sekolah->id,
            'rombel_id' => $rombel->id,
            'mata_pelajaran_id' => $mapel->id,
            'jam_per_minggu' => 2,
        ]);
        $alokasi->update(['jam_per_minggu' => 4]);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => AlokasiJamMapel::class,
            'subject_id' => $alokasi->id,
            'event' => 'updated',
        ]);

        // 7. JamKerja & HariLibur
        $jamKerja = JamKerja::factory()->create([
            'sekolah_id' => $sekolah->id,
            'hari' => 1,
            'jumlah_jam_pelajaran' => 8,
        ]);
        $jamKerja->update(['jumlah_jam_pelajaran' => 9]);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => JamKerja::class,
            'subject_id' => $jamKerja->id,
            'event' => 'updated',
        ]);

        $hariLibur = HariLibur::factory()->create([
            'sekolah_id' => $sekolah->id,
            'keterangan' => 'Libur Nasional',
        ]);
        $hariLibur->update(['keterangan' => 'Libur Nasional Diperpanjang']);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => HariLibur::class,
            'subject_id' => $hariLibur->id,
            'event' => 'updated',
        ]);
    }
}
