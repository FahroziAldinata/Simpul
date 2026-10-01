<?php

namespace Tests\Unit\Services;

use App\Enums\JenisAbsensi;
use App\Enums\StatusAbsensi;
use App\Models\JamKerja;
use App\Models\Sekolah;
use App\Services\StatusKehadiranCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StatusKehadiranCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private StatusKehadiranCalculator $calculator;

    private Sekolah $sekolah;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new StatusKehadiranCalculator;
        $this->sekolah = Sekolah::factory()->create();
    }

    public function test_masuk_tepat_waktu_atau_sebelum_jam_masuk_berstatus_hadir(): void
    {
        $jamKerja = JamKerja::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'kelompok' => 'umum',
            'hari' => 1,
            'jam_masuk' => '07:00',
            'jam_pulang' => '15:00',
            'toleransi_menit' => 15,
            'is_libur' => false,
        ]);

        $waktuAbsen = Carbon::parse('2026-10-05 06:55:00'); // Senin
        $hasil = $this->calculator->hitung($waktuAbsen, JenisAbsensi::Masuk, $jamKerja);

        $this->assertEquals(StatusAbsensi::Hadir, $hasil['status']);
        $this->assertEquals(0, $hasil['menit_terlambat']);
    }

    public function test_masuk_dalam_batas_toleransi_tetap_berstatus_hadir(): void
    {
        $jamKerja = JamKerja::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'kelompok' => 'umum',
            'hari' => 1,
            'jam_masuk' => '07:00',
            'jam_pulang' => '15:00',
            'toleransi_menit' => 15,
            'is_libur' => false,
        ]);

        // Masuk 07:14 (dalam toleransi 15 menit)
        $waktuAbsen = Carbon::parse('2026-10-05 07:14:00');
        $hasil = $this->calculator->hitung($waktuAbsen, JenisAbsensi::Masuk, $jamKerja);

        $this->assertEquals(StatusAbsensi::Hadir, $hasil['status']);
        $this->assertEquals(0, $hasil['menit_terlambat']);
    }

    public function test_masuk_melewati_toleransi_berstatus_terlambat_dan_menit_dihitung_dari_jam_masuk(): void
    {
        $jamKerja = JamKerja::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'kelompok' => 'umum',
            'hari' => 1,
            'jam_masuk' => '07:00',
            'jam_pulang' => '15:00',
            'toleransi_menit' => 15,
            'is_libur' => false,
        ]);

        // Masuk 07:25 (lewat dari toleransi 07:15)
        // Keterlambatan dihitung dari jam masuk murni 07:00 -> 25 menit
        $waktuAbsen = Carbon::parse('2026-10-05 07:25:00');
        $hasil = $this->calculator->hitung($waktuAbsen, JenisAbsensi::Masuk, $jamKerja);

        $this->assertEquals(StatusAbsensi::Terlambat, $hasil['status']);
        $this->assertEquals(25, $hasil['menit_terlambat']);
    }

    public function test_pulang_sebelum_jam_pulang_berstatus_pulang_cepat(): void
    {
        $jamKerja = JamKerja::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'kelompok' => 'umum',
            'hari' => 1,
            'jam_masuk' => '07:00',
            'jam_pulang' => '15:00',
            'toleransi_menit' => 15,
            'is_libur' => false,
        ]);

        $waktuAbsen = Carbon::parse('2026-10-05 14:40:00');
        $hasil = $this->calculator->hitung($waktuAbsen, JenisAbsensi::Pulang, $jamKerja);

        $this->assertEquals(StatusAbsensi::PulangCepat, $hasil['status']);
        $this->assertEquals(0, $hasil['menit_terlambat']);
    }

    public function test_pulang_tepat_atau_setelah_jam_pulang_berstatus_hadir(): void
    {
        $jamKerja = JamKerja::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'kelompok' => 'umum',
            'hari' => 1,
            'jam_masuk' => '07:00',
            'jam_pulang' => '15:00',
            'toleransi_menit' => 15,
            'is_libur' => false,
        ]);

        $waktuAbsen = Carbon::parse('2026-10-05 15:05:00');
        $hasil = $this->calculator->hitung($waktuAbsen, JenisAbsensi::Pulang, $jamKerja);

        $this->assertEquals(StatusAbsensi::Hadir, $hasil['status']);
        $this->assertEquals(0, $hasil['menit_terlambat']);
    }

    public function test_hari_libur_selalu_hadir_tanpa_keterlambatan(): void
    {
        $jamKerja = JamKerja::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'kelompok' => 'umum',
            'hari' => 7, // Minggu
            'is_libur' => true,
        ]);

        $waktuAbsen = Carbon::parse('2026-10-11 10:00:00');
        $hasil = $this->calculator->hitung($waktuAbsen, JenisAbsensi::Masuk, $jamKerja);

        $this->assertEquals(StatusAbsensi::Hadir, $hasil['status']);
        $this->assertEquals(0, $hasil['menit_terlambat']);
    }

    public function test_prioritas_kelompok_spesifik_dengan_fallback_ke_umum(): void
    {
        // Jam kerja umum
        JamKerja::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'kelompok' => 'umum',
            'hari' => 1,
            'jam_masuk' => '07:00',
        ]);

        // Jam kerja khusus guru
        JamKerja::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'kelompok' => 'guru',
            'hari' => 1,
            'jam_masuk' => '06:45',
        ]);

        // Guru mendapat jadwal khusus guru (06:45)
        $guruJadwal = $this->calculator->cariJamKerja($this->sekolah->id, 'guru', 1);
        $this->assertNotNull($guruJadwal);
        $this->assertEquals('06:45:00', $guruJadwal->jam_masuk);

        // Satpam yang tidak punya jadwal kelompok fallback ke 'umum' (07:00)
        $satpamJadwal = $this->calculator->cariJamKerja($this->sekolah->id, 'satpam', 1);
        $this->assertNotNull($satpamJadwal);
        $this->assertEquals('07:00:00', $satpamJadwal->jam_masuk);
    }
}
