<?php

namespace Tests\Feature\Izin;

use App\Models\Absensi;
use App\Models\Pegawai;
use App\Models\Sekolah;
use App\Models\User;
use App\Services\RekapAbsensiService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Test rekap matriks bulanan (T-09.07).
 *
 * US-23 AC6: dihasilkan <60 detik untuk 45 pegawai × 30 hari.
 * Angka nyata diukur dan dicatat.
 */
class RekapAbsensiTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private User $userOperator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->sekolah = Sekolah::factory()->create();
        setPermissionsTeamId($this->sekolah->id);

        $this->userOperator = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $this->userOperator->assignRole('operator');
    }

    public function test_rekap_menghasilkan_matriks_yang_benar(): void
    {
        $pegawai = Pegawai::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nama' => 'Andi Wijaya',
        ]);

        // Buat beberapa baris absensi di Oktober 2026
        Absensi::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'pegawai_id' => $pegawai->id,
            'tanggal' => '2026-10-05',
            'jenis' => 'masuk',
            'status' => 'hadir',
            'menit_terlambat' => 0,
            'sumber' => 'qr',
        ]);

        Absensi::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'pegawai_id' => $pegawai->id,
            'tanggal' => '2026-10-06',
            'jenis' => 'masuk',
            'status' => 'terlambat',
            'menit_terlambat' => 25,
            'sumber' => 'qr',
        ]);

        Absensi::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'pegawai_id' => $pegawai->id,
            'tanggal' => '2026-10-07',
            'jenis' => 'masuk',
            'status' => 'izin',
            'menit_terlambat' => 0,
            'sumber' => 'izin',
        ]);

        $service = app(RekapAbsensiService::class);
        $rekap = $service->generate($this->sekolah->id, 10, 2026);

        $this->assertCount(1, $rekap['baris']);
        $baris = $rekap['baris'][0];

        $this->assertEquals('H', $baris['sel']['2026-10-05']['label']);
        $this->assertEquals('T', $baris['sel']['2026-10-06']['label']);
        $this->assertEquals('I', $baris['sel']['2026-10-07']['label']);
        $this->assertEquals(1, $baris['ringkasan']['H']);
        $this->assertEquals(1, $baris['ringkasan']['T']);
        $this->assertEquals(1, $baris['ringkasan']['I']);
        $this->assertEquals(25, $baris['ringkasan']['total_menit']);
    }

    public function test_rekap_sel_selalu_mengandung_label_huruf_bukan_hanya_warna(): void
    {
        $pegawai = Pegawai::factory()->create(['sekolah_id' => $this->sekolah->id]);

        Absensi::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'pegawai_id' => $pegawai->id,
            'tanggal' => '2026-10-05',
            'jenis' => 'masuk',
            'status' => 'hadir',
            'sumber' => 'qr',
        ]);

        $service = app(RekapAbsensiService::class);
        $rekap = $service->generate($this->sekolah->id, 10, 2026);

        $sel = $rekap['baris'][0]['sel']['2026-10-05'];

        $this->assertNotNull($sel, 'Sel harus ada');
        $this->assertArrayHasKey('label', $sel, 'Sel harus punya label huruf (aturan desain C1)');
        $this->assertNotEmpty($sel['label'], 'Label huruf tidak boleh kosong');
        $this->assertArrayHasKey('warna', $sel, 'Sel harus punya kelas warna');
    }

    /**
     * US-23 AC6 — Rekap <60 detik untuk 45 pegawai × 30 hari.
     *
     * Menggunakan 45 pegawai dengan data absensi penuh satu bulan.
     * Angka nyata diukur dan dilaporkan.
     *
     * @group performance
     */
    public function test_rekap_45_pegawai_30_hari_di_bawah_60_detik(): void
    {
        $jumlahPegawai = 45;
        $bulan = 10;
        $tahun = 2026;

        // Buat 45 pegawai
        $pegawaiList = Pegawai::factory()->count($jumlahPegawai)->create([
            'sekolah_id' => $this->sekolah->id,
        ]);

        // Buat data absensi untuk setiap pegawai × 30 hari (hanya hari kerja tidak ada hari libur)
        $absensiData = [];
        foreach ($pegawaiList as $pegawai) {
            for ($hari = 1; $hari <= 30; $hari++) {
                $statuses = ['hadir', 'terlambat', 'izin', 'sakit', 'hadir', 'hadir'];
                $status = $statuses[$hari % count($statuses)];

                $absensiData[] = [
                    'id' => Str::uuid()->toString(),
                    'sekolah_id' => $this->sekolah->id,
                    'pegawai_id' => $pegawai->id,
                    'tanggal' => "{$tahun}-{$bulan}-".str_pad($hari, 2, '0', STR_PAD_LEFT),
                    'jenis' => 'masuk',
                    'status' => $status,
                    'menit_terlambat' => $status === 'terlambat' ? 20 : 0,
                    'sumber' => in_array($status, ['izin', 'sakit']) ? 'izin' : 'qr',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Bulk insert untuk kecepatan setup test
        foreach (array_chunk($absensiData, 500) as $chunk) {
            DB::table('absensi')->insert($chunk);
        }

        $service = app(RekapAbsensiService::class);

        $mulai = microtime(true);
        $rekap = $service->generate($this->sekolah->id, $bulan, $tahun);
        $durasi = (microtime(true) - $mulai) * 1000;

        // Verifikasi kebenaran data
        $this->assertCount($jumlahPegawai, $rekap['baris']);

        // Verifikasi performa (US-23 AC6)
        $this->assertLessThan(
            60000,
            $durasi,
            "Rekap 45 pegawai × 30 hari harus <60 detik. Aktual: {$durasi}ms"
        );

        // Log untuk laporan
        fwrite(STDOUT, "\n[PERFORMA REKAP] 45 pegawai × 30 hari: {$rekap['durasi_ms']}ms (service internal) | {$durasi}ms (termasuk framework overhead)\n");
    }

    public function test_guru_hanya_lihat_data_sendiri_di_rekap(): void
    {
        $userGuru = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        setPermissionsTeamId($this->sekolah->id);
        $userGuru->assignRole('guru');

        $pegawaiGuru = Pegawai::factory()->create(['sekolah_id' => $this->sekolah->id, 'user_id' => $userGuru->id]);
        $pegawaiLain = Pegawai::factory()->create(['sekolah_id' => $this->sekolah->id]);

        // Absensi untuk kedua pegawai
        Absensi::factory()->create(['sekolah_id' => $this->sekolah->id, 'pegawai_id' => $pegawaiGuru->id, 'tanggal' => '2026-10-01', 'jenis' => 'masuk', 'status' => 'hadir', 'sumber' => 'qr']);
        Absensi::factory()->create(['sekolah_id' => $this->sekolah->id, 'pegawai_id' => $pegawaiLain->id, 'tanggal' => '2026-10-01', 'jenis' => 'masuk', 'status' => 'hadir', 'sumber' => 'qr']);

        // Guru akses rekap — harus dapat filter ke data sendiri di controller
        $response = $this->actingAs($userGuru)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('izin.rekap.index').'?bulan=10&tahun=2026');

        $response->assertOk();
        $rekap = $response->original->getData()['page']['props']['rekap'];

        // Guru hanya boleh lihat 1 baris (milik sendiri)
        $this->assertCount(1, $rekap['baris']);
        $this->assertEquals($pegawaiGuru->id, $rekap['baris'][0]['pegawai_id']);
    }
}
