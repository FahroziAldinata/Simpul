<?php

namespace Tests\Feature\Jadwal;

use App\Models\AlokasiJamMapel;
use App\Models\JadwalPelajaran;
use App\Models\JamKerja;
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
        $this->ruang1 = Ruang::factory()->create(['sekolah_id' => $this->sekolah->id, 'nama' => 'Lab Komputer 1', 'kategori' => 'laboratorium']);
        $this->ruang2 = Ruang::factory()->create(['sekolah_id' => $this->sekolah->id, 'nama' => 'Ruang Teori 101', 'kategori' => 'teori']);
        $this->rombel1 = Rombel::factory()->create(['sekolah_id' => $this->sekolah->id, 'semester_id' => $this->semester->id, 'nama' => 'X RPL 1']);
        $this->rombel2 = Rombel::factory()->create(['sekolah_id' => $this->sekolah->id, 'semester_id' => $this->semester->id, 'nama' => 'XI TKJ 2']);
        $this->mapel1 = MataPelajaran::factory()->create(['sekolah_id' => $this->sekolah->id, 'nama' => 'Pemrograman Web', 'butuh_ruang_kategori' => null]);
        $this->mapel2 = MataPelajaran::factory()->create(['sekolah_id' => $this->sekolah->id, 'nama' => 'Basis Data', 'butuh_ruang_kategori' => null]);

        $this->detector = new ConflictDetector;
    }

    public function test_conflict_detector_mendeteksi_bentrok_guru_dengan_pesan_manusiawi(): void
    {
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
        $this->assertStringContainsString('Budi Santoso', (string) $result->firstMessage());
        $this->assertStringContainsString('X RPL 1', (string) $result->firstMessage());
        $this->assertStringContainsString('jam ke-1–3', (string) $result->firstMessage());
        $this->assertStringContainsString('Senin', (string) $result->firstMessage());
    }

    public function test_conflict_detector_mendeteksi_bentrok_ruang_dengan_pesan_manusiawi(): void
    {
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
        $this->assertStringContainsString('Lab Komputer 1', (string) $result->firstMessage());
        $this->assertStringContainsString('X RPL 1', (string) $result->firstMessage());
        $this->assertStringContainsString('Selasa', (string) $result->firstMessage());
    }

    public function test_conflict_detector_mendeteksi_bentrok_rombel_dengan_pesan_manusiawi(): void
    {
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
        $this->assertStringContainsString('X RPL 1', (string) $result->firstMessage());
        $this->assertStringContainsString('Pemrograman Web', (string) $result->firstMessage());
        $this->assertStringContainsString('Budi Santoso', (string) $result->firstMessage());
        $this->assertStringContainsString('Rabu', (string) $result->firstMessage());
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
        $jadwal->delete();

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

    /**
     * T-11.05 (H4): Over-allocation dicegah jika total jam melebihi alokasi kurikulum.
     */
    public function test_h4_mencegah_over_allocation_melebihi_alokasi_jam_mapel(): void
    {
        // Alokasi Pemrograman Web untuk X RPL 1 adalah 4 jam per minggu
        AlokasiJamMapel::create([
            'sekolah_id' => $this->sekolah->id,
            'rombel_id' => $this->rombel1->id,
            'mata_pelajaran_id' => $this->mapel1->id,
            'jam_per_minggu' => 4,
        ]);

        // Sudah terjadwal 3 jam (jam 1 s.d. 4 pada hari Senin)
        JadwalPelajaran::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel1->id,
            'mata_pelajaran_id' => $this->mapel1->id,
            'guru_id' => $this->guru1->id,
            'ruang_id' => $this->ruang1->id,
            'hari' => 1,
            'jam_mulai_ke' => 1,
            'jam_selesai_ke' => 4,
        ]);

        // Coba jadwalkan slot baru 2 jam lagi di hari Selasa (total jadi 5 jam > 4 jam) -> HARUS DITOLAK
        $result = $this->detector->check([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel1->id,
            'mata_pelajaran_id' => $this->mapel1->id,
            'guru_id' => $this->guru1->id,
            'ruang_id' => $this->ruang1->id,
            'hari' => 2,
            'jam_mulai_ke' => 1,
            'jam_selesai_ke' => 3, // 2 jam
        ]);

        $this->assertTrue($result->hasConflict());
        $this->assertSame('alokasi', $result->conflicts[0]->type);
        $this->assertStringContainsString('melebihi batas (3/4 jam)', (string) $result->firstMessage());

        // Namun jika menambah 1 jam (total pas 4 jam) -> DIIZINKAN
        $validResult = $this->detector->check([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel1->id,
            'mata_pelajaran_id' => $this->mapel1->id,
            'guru_id' => $this->guru1->id,
            'ruang_id' => $this->ruang1->id,
            'hari' => 2,
            'jam_mulai_ke' => 1,
            'jam_selesai_ke' => 2, // 1 jam
        ]);

        $this->assertFalse($validResult->hasConflict());
    }

    /**
     * T-11.05 (H5): Ditolak jika jatuh di hari libur operasional atau di luar jam operasional.
     */
    public function test_h5_menolak_jadwal_pada_hari_libur_atau_luar_jam_operasional(): void
    {
        // Konfigurasi jam_kerja: Jumat (hari 5) hanya 6 jam pelajaran, Sabtu (hari 6) libur
        JamKerja::create([
            'sekolah_id' => $this->sekolah->id,
            'kelompok' => 'umum',
            'hari' => 5,
            'is_libur' => false,
            'jumlah_jam_pelajaran' => 6,
        ]);
        JamKerja::create([
            'sekolah_id' => $this->sekolah->id,
            'kelompok' => 'umum',
            'hari' => 6,
            'is_libur' => true,
            'jumlah_jam_pelajaran' => 0,
        ]);

        // Coba jadwal di hari Sabtu (hari libur) -> HARUS DITOLAK
        $resultSabtu = $this->detector->check([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel1->id,
            'mata_pelajaran_id' => $this->mapel1->id,
            'guru_id' => $this->guru1->id,
            'ruang_id' => $this->ruang1->id,
            'hari' => 6,
            'jam_mulai_ke' => 1,
            'jam_selesai_ke' => 3,
        ]);

        $this->assertTrue($resultSabtu->hasConflict());
        $this->assertSame('hari_libur', $resultSabtu->conflicts[0]->type);
        $this->assertStringContainsString('hari libur', (string) $resultSabtu->firstMessage());

        // Coba jadwal di hari Jumat jam ke-6 s.d. 8 (jam selesai 8 > maks 6+1=7) -> HARUS DITOLAK
        $resultJumatLuar = $this->detector->check([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel1->id,
            'mata_pelajaran_id' => $this->mapel1->id,
            'guru_id' => $this->guru1->id,
            'ruang_id' => $this->ruang1->id,
            'hari' => 5,
            'jam_mulai_ke' => 5,
            'jam_selesai_ke' => 8,
        ]);

        $this->assertTrue($resultJumatLuar->hasConflict());
        $this->assertSame('jam_operasional', $resultJumatLuar->conflicts[0]->type);
        $this->assertStringContainsString('luar jam operasional', (string) $resultJumatLuar->firstMessage());

        // Jadwal di hari Jumat jam ke-5 s.d. 7 (mencakup jam 5 dan 6, maks 6) -> DIIZINKAN
        $resultJumatValid = $this->detector->check([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel1->id,
            'mata_pelajaran_id' => $this->mapel1->id,
            'guru_id' => $this->guru1->id,
            'ruang_id' => $this->ruang1->id,
            'hari' => 5,
            'jam_mulai_ke' => 5,
            'jam_selesai_ke' => 7,
        ]);

        $this->assertFalse($resultJumatValid->hasConflict());
    }

    /**
     * T-11.05 (H6): Mapel butuh ruang khusus (Lab) hanya boleh di ruang berkategori cocok.
     */
    public function test_h6_validasi_kategori_ruang_khusus(): void
    {
        // Mapel Praktikum Kimia membutuhkan ruang 'laboratorium'
        $mapelLab = MataPelajaran::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nama' => 'Praktikum Kimia',
            'butuh_ruang_kategori' => 'laboratorium',
        ]);

        // Coba simpan tanpa ruang (ruang null) -> HARUS DITOLAK
        $resultNull = $this->detector->check([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel1->id,
            'mata_pelajaran_id' => $mapelLab->id,
            'guru_id' => $this->guru1->id,
            'ruang_id' => null,
            'hari' => 1,
            'jam_mulai_ke' => 1,
            'jam_selesai_ke' => 3,
        ]);

        $this->assertTrue($resultNull->hasConflict());
        $this->assertSame('ruang_kategori', $resultNull->conflicts[0]->type);
        $this->assertStringContainsString("memerlukan ruang berkategori 'laboratorium'", (string) $resultNull->firstMessage());

        // Coba simpan di Ruang Teori 101 (kategori 'teori') -> HARUS DITOLAK
        $resultSalahKategori = $this->detector->check([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel1->id,
            'mata_pelajaran_id' => $mapelLab->id,
            'guru_id' => $this->guru1->id,
            'ruang_id' => $this->ruang2->id, // ruang teori
            'hari' => 1,
            'jam_mulai_ke' => 1,
            'jam_selesai_ke' => 3,
        ]);

        $this->assertTrue($resultSalahKategori->hasConflict());
        $this->assertSame('ruang_kategori', $resultSalahKategori->conflicts[0]->type);
        $this->assertStringContainsString("sedangkan ruang Ruang Teori 101 berkategori 'teori'", (string) $resultSalahKategori->firstMessage());

        // Simpan di Lab Komputer 1 (kategori 'laboratorium') -> DIIZINKAN
        $resultCocok = $this->detector->check([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel1->id,
            'mata_pelajaran_id' => $mapelLab->id,
            'guru_id' => $this->guru1->id,
            'ruang_id' => $this->ruang1->id, // laboratorium
            'hari' => 1,
            'jam_mulai_ke' => 1,
            'jam_selesai_ke' => 3,
        ]);

        $this->assertFalse($resultCocok->hasConflict());
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
        $this->assertStringContainsString('Budi Santoso', (string) $result->firstMessage());

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

        // Uji H4 in-memory (alokasi 3 jam, sudah ada 2 jam, coba tambah 2 jam lagi -> total 4 > 3)
        $resultAlokasi = $this->detector->checkInMemory([
            'hari' => 2,
            'jam_mulai_ke' => 1,
            'jam_selesai_ke' => 3, // 2 jam
            'guru_id' => $this->guru2->id,
            'rombel_id' => $this->rombel1->id,
            'mata_pelajaran_id' => $this->mapel1->id,
        ], $existing, context: [
            'alokasi_jam' => 3,
            'nama_mapel' => 'Pemrograman Web',
            'nama_rombel' => 'X RPL 1',
        ]);

        $this->assertTrue($resultAlokasi->hasConflict());
        $this->assertSame('alokasi', $resultAlokasi->conflicts[0]->type);
    }
}
