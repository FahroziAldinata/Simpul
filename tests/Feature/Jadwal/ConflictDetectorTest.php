<?php

namespace Tests\Feature\Jadwal;

use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Rombel;
use App\Models\Ruang;
use App\Models\Sekolah;
use App\Models\Semester;
use App\Services\Jadwal\ConflictDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConflictDetectorTest extends TestCase
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

    private ConflictDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sekolah = Sekolah::factory()->create();
        $this->semester = Semester::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $this->guru1 = Pegawai::factory()->create(['sekolah_id' => $this->sekolah->id, 'nama' => 'Budi Santoso, S.Pd.']);
        $this->guru2 = Pegawai::factory()->create(['sekolah_id' => $this->sekolah->id, 'nama' => 'Siti Aminah, M.Kom.']);
        $this->ruang1 = Ruang::factory()->create(['sekolah_id' => $this->sekolah->id, 'nama' => 'Lab Komputer 1']);
        $this->ruang2 = Ruang::factory()->create(['sekolah_id' => $this->sekolah->id, 'nama' => 'Ruang Teori 101']);
        $this->rombel1 = Rombel::factory()->create(['sekolah_id' => $this->sekolah->id, 'semester_id' => $this->semester->id, 'nama' => 'X RPL 1']);
        $this->rombel2 = Rombel::factory()->create(['sekolah_id' => $this->sekolah->id, 'semester_id' => $this->semester->id, 'nama' => 'XI TKJ 2']);
        $this->mapel1 = MataPelajaran::factory()->create(['sekolah_id' => $this->sekolah->id, 'nama' => 'Pemrograman Web']);
        $this->mapel2 = MataPelajaran::factory()->create(['sekolah_id' => $this->sekolah->id, 'nama' => 'Basis Data']);

        $this->detector = new ConflictDetector;
    }

    public function test_conflict_detector_mendeteksi_bentrok_guru_dengan_pesan_manusiawi(): void
    {
        // Jadwal yang sudah ada: Budi Santoso mengajar di X RPL 1 hari Senin jam 1..3
        JadwalPelajaran::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel1->id,
            'mata_pelajaran_id' => $this->mapel1->id,
            'guru_id' => $this->guru1->id,
            'ruang_id' => $this->ruang1->id,
            'hari' => 1,
            'jam_mulai_ke' => 1,
            'jam_selesai_ke' => 3,
        ]);

        // Coba jadwalkan Budi Santoso di XI TKJ 2 hari Senin jam 2..4 (overlap jam 2)
        $result = $this->detector->check([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel2->id,
            'mata_pelajaran_id' => $this->mapel2->id,
            'guru_id' => $this->guru1->id,
            'ruang_id' => $this->ruang2->id,
            'hari' => 1,
            'jam_mulai_ke' => 2,
            'jam_selesai_ke' => 4,
        ]);

        $this->assertTrue($result->hasConflict());
        $this->assertStringContainsString('Budi Santoso', $result->firstMessage());
        $this->assertStringContainsString('X RPL 1', $result->firstMessage());
        $this->assertStringContainsString('jam ke-1–3', $result->firstMessage());
        $this->assertStringContainsString('Senin', $result->firstMessage());
    }

    public function test_conflict_detector_mendeteksi_bentrok_ruang_dengan_pesan_manusiawi(): void
    {
        // Jadwal yang sudah ada: Lab Komputer 1 dipakai Selasa jam 3..5
        JadwalPelajaran::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel1->id,
            'mata_pelajaran_id' => $this->mapel1->id,
            'guru_id' => $this->guru1->id,
            'ruang_id' => $this->ruang1->id,
            'hari' => 2,
            'jam_mulai_ke' => 3,
            'jam_selesai_ke' => 5,
        ]);

        // Coba jadwalkan ruang yang sama untuk guru2 dan rombel2 di jam overlap
        $result = $this->detector->check([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel2->id,
            'mata_pelajaran_id' => $this->mapel2->id,
            'guru_id' => $this->guru2->id,
            'ruang_id' => $this->ruang1->id,
            'hari' => 2,
            'jam_mulai_ke' => 4,
            'jam_selesai_ke' => 6,
        ]);

        $this->assertTrue($result->hasConflict());
        $this->assertStringContainsString('Lab Komputer 1', $result->firstMessage());
        $this->assertStringContainsString('X RPL 1', $result->firstMessage());
        $this->assertStringContainsString('Selasa', $result->firstMessage());
    }

    public function test_conflict_detector_mendeteksi_bentrok_rombel_dengan_pesan_manusiawi(): void
    {
        // Jadwal yang sudah ada: X RPL 1 belajar Rabu jam 1..3
        JadwalPelajaran::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel1->id,
            'mata_pelajaran_id' => $this->mapel1->id,
            'guru_id' => $this->guru1->id,
            'ruang_id' => $this->ruang1->id,
            'hari' => 3,
            'jam_mulai_ke' => 1,
            'jam_selesai_ke' => 3,
        ]);

        // Coba jadwalkan X RPL 1 dengan guru lain di jam overlap
        $result = $this->detector->check([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel1->id,
            'mata_pelajaran_id' => $this->mapel2->id,
            'guru_id' => $this->guru2->id,
            'ruang_id' => $this->ruang2->id,
            'hari' => 3,
            'jam_mulai_ke' => 2,
            'jam_selesai_ke' => 4,
        ]);

        $this->assertTrue($result->hasConflict());
        $this->assertStringContainsString('X RPL 1', $result->firstMessage());
        $this->assertStringContainsString('Pemrograman Web', $result->firstMessage());
        $this->assertStringContainsString('Budi Santoso', $result->firstMessage());
        $this->assertStringContainsString('Rabu', $result->firstMessage());
    }

    public function test_conflict_detector_mengabaikan_record_yang_sedang_diedit(): void
    {
        $jadwal = JadwalPelajaran::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel1->id,
            'mata_pelajaran_id' => $this->mapel1->id,
            'guru_id' => $this->guru1->id,
            'ruang_id' => $this->ruang1->id,
            'hari' => 1,
            'jam_mulai_ke' => 1,
            'jam_selesai_ke' => 3,
        ]);

        // Validasi slot yang sama persis tapi dengan ignoreId = id jadwal saat ini (misal update ganti ruang)
        $result = $this->detector->check([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel1->id,
            'mata_pelajaran_id' => $this->mapel1->id,
            'guru_id' => $this->guru1->id,
            'ruang_id' => $this->ruang2->id,
            'hari' => 1,
            'jam_mulai_ke' => 1,
            'jam_selesai_ke' => 3,
        ], ignoreId: $jadwal->id);

        $this->assertFalse($result->hasConflict());
    }

    public function test_conflict_detector_mengabaikan_record_yang_sudah_soft_deleted(): void
    {
        $jadwal = JadwalPelajaran::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel1->id,
            'mata_pelajaran_id' => $this->mapel1->id,
            'guru_id' => $this->guru1->id,
            'ruang_id' => $this->ruang1->id,
            'hari' => 1,
            'jam_mulai_ke' => 1,
            'jam_selesai_ke' => 3,
        ]);
        $jadwal->delete(); // Soft delete

        // Slot yang sama untuk jadwal baru
        $result = $this->detector->check([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel1->id,
            'mata_pelajaran_id' => $this->mapel1->id,
            'guru_id' => $this->guru1->id,
            'ruang_id' => $this->ruang1->id,
            'hari' => 1,
            'jam_mulai_ke' => 1,
            'jam_selesai_ke' => 3,
        ]);

        $this->assertFalse($result->hasConflict());
    }

    public function test_conflict_detector_in_memory_mendeteksi_bentrok_secara_akurat(): void
    {
        $item = JadwalPelajaran::factory()->make([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel1->id,
            'mata_pelajaran_id' => $this->mapel1->id,
            'guru_id' => $this->guru1->id,
            'ruang_id' => $this->ruang1->id,
            'hari' => 1,
            'jam_mulai_ke' => 1,
            'jam_selesai_ke' => 3,
        ]);
        $item->setRelation('guru', $this->guru1);
        $item->setRelation('rombel', $this->rombel1);
        $item->setRelation('ruang', $this->ruang1);
        $item->setRelation('mataPelajaran', $this->mapel1);

        $existing = collect([$item]);

        // Uji bentrok guru in-memory
        $result = $this->detector->checkInMemory([
            'hari' => 1,
            'jam_mulai_ke' => 2,
            'jam_selesai_ke' => 4,
            'guru_id' => $this->guru1->id,
            'rombel_id' => $this->rombel2->id,
            'ruang_id' => $this->ruang2->id,
        ], $existing);

        $this->assertTrue($result->hasConflict());
        $this->assertStringContainsString('Budi Santoso', $result->firstMessage());

        // Uji slot tidak overlap in-memory (jam 3..5)
        $noOverlap = $this->detector->checkInMemory([
            'hari' => 1,
            'jam_mulai_ke' => 3,
            'jam_selesai_ke' => 5,
            'guru_id' => $this->guru1->id,
            'rombel_id' => $this->rombel2->id,
            'ruang_id' => $this->ruang2->id,
        ], $existing);

        $this->assertFalse($noOverlap->hasConflict());
    }
}
