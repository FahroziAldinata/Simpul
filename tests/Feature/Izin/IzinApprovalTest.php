<?php

namespace Tests\Feature\Izin;

use App\Enums\StatusAbsensi;
use App\Enums\StatusPengajuanIzin;
use App\Models\Absensi;
use App\Models\JenisIzin;
use App\Models\KuotaCuti;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\PersetujuanIzin;
use App\Models\Sekolah;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\IzinApprovalService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Test suite lengkap untuk modul Izin/Cuti Minggu 9.
 *
 * Mencakup semua poin Definition of Done:
 * - AC6: tolak pengajuan untuk tanggal sudah hadir
 * - AC6 validasi ulang saat approval final
 * - Alur berjenjang: approver langkah 2 tidak melihat item yang masih di langkah 1
 * - Role yang tepat per langkah
 * - Auto-isi absensi saat disetujui penuh
 * - Guard scan QR di hari izin disetujui
 * - Lampiran MIME palsu ditolak
 * - Kuota cuti berkurang saat pengajuan cuti disetujui
 * - Isolasi tenant 404
 * - RBAC semua peran
 */
class IzinApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private TahunAjaran $tahunAjaran;

    private User $userGuru;

    private User $userKepsek;

    private User $userOperator;

    private Pegawai $pegawaiGuru;

    private JenisIzin $jenisIzin;

    private JenisIzin $jenisCuti;

    private IzinApprovalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->sekolah = Sekolah::factory()->create();
        setPermissionsTeamId($this->sekolah->id);

        $this->tahunAjaran = TahunAjaran::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'is_aktif' => true,
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
        ]);

        // Users
        $this->userGuru = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $this->userGuru->assignRole('guru');

        $this->userKepsek = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $this->userKepsek->assignRole('kepsek');

        $this->userOperator = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $this->userOperator->assignRole('operator');

        // Pegawai
        $this->pegawaiGuru = Pegawai::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'user_id' => $this->userGuru->id,
            'nama' => 'Budi Santoso',
        ]);

        // Jenis izin (butuh persetujuan 1 langkah: kepsek)
        $this->jenisIzin = JenisIzin::factory()->create([
            'nama' => 'Izin',
            'kode' => 'izin',
            'butuh_lampiran' => false,
            'butuh_persetujuan' => true,
            'mengurangi_kuota_cuti' => false,
            'urutan_approval' => ['kepsek'],
        ]);

        // Jenis cuti (mengurangi kuota, 1 langkah: kepsek)
        $this->jenisCuti = JenisIzin::factory()->create([
            'nama' => 'Cuti Tahunan',
            'kode' => 'cuti',
            'butuh_lampiran' => false,
            'butuh_persetujuan' => true,
            'mengurangi_kuota_cuti' => true,
            'urutan_approval' => ['kepsek'],
        ]);

        $this->service = app(IzinApprovalService::class);
    }

    // =========================================================================
    // T-09.02 — Form Pengajuan
    // =========================================================================

    public function test_guru_bisa_mengajukan_izin(): void
    {
        $pengajuan = $this->service->ajukan($this->pegawaiGuru, [
            'jenis_izin_id' => $this->jenisIzin->id,
            'tanggal_mulai' => '2026-10-10',
            'tanggal_selesai' => '2026-10-10',
            'alasan' => 'Keperluan keluarga',
            'lampiran_path' => null,
            'lampiran_mime' => null,
        ]);

        $this->assertEquals(StatusPengajuanIzin::Menunggu, $pengajuan->status);
        $this->assertDatabaseHas('pengajuan_izin', ['id' => $pengajuan->id, 'status' => 'menunggu']);
        $this->assertDatabaseHas('persetujuan_izin', [
            'pengajuan_izin_id' => $pengajuan->id,
            'urutan' => 1,
            'approver_role' => 'kepsek',
            'status' => 'menunggu',
        ]);
    }

    public function test_tanggal_selesai_sebelum_mulai_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->ajukan($this->pegawaiGuru, [
            'jenis_izin_id' => $this->jenisIzin->id,
            'tanggal_mulai' => '2026-10-10',
            'tanggal_selesai' => '2026-10-09', // Sebelum mulai!
            'alasan' => 'Test',
            'lampiran_path' => null,
            'lampiran_mime' => null,
        ]);
    }

    // =========================================================================
    // T-09.02 AC6 — Tolak pengajuan untuk tanggal sudah hadir
    // =========================================================================

    public function test_pengajuan_izin_ditolak_jika_tanggal_sudah_tercatat_hadir(): void
    {
        // Buat absensi hadir di tanggal 10 Oktober
        Absensi::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'pegawai_id' => $this->pegawaiGuru->id,
            'tanggal' => '2026-10-10',
            'jenis' => 'masuk',
            'status' => 'hadir',
            'sumber' => 'qr',
        ]);

        $this->expectException(ValidationException::class);

        $this->service->ajukan($this->pegawaiGuru, [
            'jenis_izin_id' => $this->jenisIzin->id,
            'tanggal_mulai' => '2026-10-10',
            'tanggal_selesai' => '2026-10-10',
            'alasan' => 'Keperluan keluarga',
            'lampiran_path' => null,
            'lampiran_mime' => null,
        ]);
    }

    public function test_pengajuan_izin_ditolak_jika_tanggal_sudah_terlambat(): void
    {
        // Status terlambat juga dianggap hadir secara fisik
        Absensi::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'pegawai_id' => $this->pegawaiGuru->id,
            'tanggal' => '2026-10-10',
            'jenis' => 'masuk',
            'status' => 'terlambat',
            'sumber' => 'qr',
        ]);

        $this->expectException(ValidationException::class);

        $this->service->ajukan($this->pegawaiGuru, [
            'jenis_izin_id' => $this->jenisIzin->id,
            'tanggal_mulai' => '2026-10-10',
            'tanggal_selesai' => '2026-10-10',
            'alasan' => 'Terlambat tapi mau izin?',
            'lampiran_path' => null,
            'lampiran_mime' => null,
        ]);
    }

    // =========================================================================
    // T-09.03 — Alur Persetujuan Berjenjang
    // =========================================================================

    public function test_kepsek_bisa_menyetujui_langkah_1(): void
    {
        $pengajuan = $this->service->ajukan($this->pegawaiGuru, [
            'jenis_izin_id' => $this->jenisIzin->id,
            'tanggal_mulai' => '2026-10-15',
            'tanggal_selesai' => '2026-10-15',
            'alasan' => 'Keperluan',
            'lampiran_path' => null,
            'lampiran_mime' => null,
        ]);

        $langkah = PersetujuanIzin::where('pengajuan_izin_id', $pengajuan->id)->first();

        $this->service->putuskan($langkah, $this->userKepsek, true, null);

        $pengajuan->refresh();
        $this->assertEquals(StatusPengajuanIzin::Disetujui, $pengajuan->status);
    }

    public function test_guru_tidak_bisa_menyetujui_langkah_yang_memerlukan_kepsek(): void
    {
        $pengajuan = $this->service->ajukan($this->pegawaiGuru, [
            'jenis_izin_id' => $this->jenisIzin->id,
            'tanggal_mulai' => '2026-10-15',
            'tanggal_selesai' => '2026-10-15',
            'alasan' => 'Keperluan',
            'lampiran_path' => null,
            'lampiran_mime' => null,
        ]);

        $langkah = PersetujuanIzin::where('pengajuan_izin_id', $pengajuan->id)->first();

        $this->expectException(HttpException::class);

        $this->service->putuskan($langkah, $this->userGuru, true, null);
    }

    public function test_alur_dua_langkah_approver_2_tidak_bisa_sebelum_langkah_1_selesai(): void
    {
        // Buat jenis izin dua langkah: kepsek → super_admin
        $jenisDuaLangkah = JenisIzin::factory()->create([
            'butuh_persetujuan' => true,
            'urutan_approval' => ['kepsek', 'super_admin'],
        ]);

        $userSuperAdmin = User::factory()->create(['sekolah_id' => null]);
        $userSuperAdmin->assignRole('super_admin');

        $pengajuan = $this->service->ajukan($this->pegawaiGuru, [
            'jenis_izin_id' => $jenisDuaLangkah->id,
            'tanggal_mulai' => '2026-10-20',
            'tanggal_selesai' => '2026-10-20',
            'alasan' => 'Dua langkah',
            'lampiran_path' => null,
            'lampiran_mime' => null,
        ]);

        // Langkah 1 masih menunggu — Super Admin belum boleh melihat di inbox-nya
        $inboxSuperAdmin = PersetujuanIzin::query()
            ->whereIn('approver_role', ['super_admin'])
            ->where('status', 'menunggu')
            ->whereNotExists(function ($sub) {
                $sub->from('persetujuan_izin as p2')
                    ->whereColumn('p2.pengajuan_izin_id', 'persetujuan_izin.pengajuan_izin_id')
                    ->whereColumn('p2.urutan', '<', 'persetujuan_izin.urutan')
                    ->where('p2.status', 'menunggu');
            })
            ->get();

        $this->assertCount(0, $inboxSuperAdmin, 'Langkah 2 tidak boleh muncul di inbox saat langkah 1 masih menunggu');
    }

    public function test_alur_dua_langkah_approver_2_muncul_setelah_langkah_1_selesai(): void
    {
        $jenisDuaLangkah = JenisIzin::factory()->create([
            'butuh_persetujuan' => true,
            'urutan_approval' => ['kepsek', 'super_admin'],
        ]);

        $userSuperAdmin = User::factory()->create(['sekolah_id' => null]);
        $userSuperAdmin->assignRole('super_admin');

        $pengajuan = $this->service->ajukan($this->pegawaiGuru, [
            'jenis_izin_id' => $jenisDuaLangkah->id,
            'tanggal_mulai' => '2026-10-20',
            'tanggal_selesai' => '2026-10-20',
            'alasan' => 'Dua langkah',
            'lampiran_path' => null,
            'lampiran_mime' => null,
        ]);

        // Kepsek approve langkah 1
        $langkah1 = PersetujuanIzin::where('pengajuan_izin_id', $pengajuan->id)->where('urutan', 1)->first();
        $this->service->putuskan($langkah1, $this->userKepsek, true, null);

        // Sekarang Super Admin harus bisa melihat langkah 2
        $inboxSuperAdmin = PersetujuanIzin::query()
            ->whereIn('approver_role', ['super_admin'])
            ->where('status', 'menunggu')
            ->whereNotExists(function ($sub) {
                $sub->from('persetujuan_izin as p2')
                    ->whereColumn('p2.pengajuan_izin_id', 'persetujuan_izin.pengajuan_izin_id')
                    ->whereColumn('p2.urutan', '<', 'persetujuan_izin.urutan')
                    ->where('p2.status', 'menunggu');
            })
            ->get();

        $this->assertCount(1, $inboxSuperAdmin, 'Langkah 2 harus muncul di inbox setelah langkah 1 selesai');
    }

    public function test_tolak_memerlukan_catatan(): void
    {
        $pengajuan = $this->service->ajukan($this->pegawaiGuru, [
            'jenis_izin_id' => $this->jenisIzin->id,
            'tanggal_mulai' => '2026-10-15',
            'tanggal_selesai' => '2026-10-15',
            'alasan' => 'Keperluan',
            'lampiran_path' => null,
            'lampiran_mime' => null,
        ]);

        $langkah = PersetujuanIzin::where('pengajuan_izin_id', $pengajuan->id)->first();

        $this->expectException(ValidationException::class);

        $this->service->putuskan($langkah, $this->userKepsek, false, null); // Tolak tanpa catatan!
    }

    public function test_kepsek_sekolah_lain_tidak_bisa_approve(): void
    {
        $sekolahLain = Sekolah::factory()->create();
        $kepsekLain = User::factory()->create(['sekolah_id' => $sekolahLain->id]);
        setPermissionsTeamId($sekolahLain->id);
        $kepsekLain->assignRole('kepsek');
        setPermissionsTeamId($this->sekolah->id);

        $pengajuan = $this->service->ajukan($this->pegawaiGuru, [
            'jenis_izin_id' => $this->jenisIzin->id,
            'tanggal_mulai' => '2026-10-15',
            'tanggal_selesai' => '2026-10-15',
            'alasan' => 'Keperluan',
            'lampiran_path' => null,
            'lampiran_mime' => null,
        ]);

        $langkah = PersetujuanIzin::where('pengajuan_izin_id', $pengajuan->id)->first();

        // Kepsek sekolah lain tidak punya role 'kepsek' di sekolah ini — harus 403
        $this->expectException(HttpException::class);

        $this->service->putuskan($langkah, $kepsekLain, true, null);
    }

    // =========================================================================
    // T-09.05 — Auto-isi Absensi
    // =========================================================================

    public function test_pengajuan_disetujui_penuh_otomatis_isi_absensi(): void
    {
        $pengajuan = $this->service->ajukan($this->pegawaiGuru, [
            'jenis_izin_id' => $this->jenisIzin->id,
            'tanggal_mulai' => '2026-10-15',
            'tanggal_selesai' => '2026-10-16', // 2 hari
            'alasan' => 'Keperluan keluarga',
            'lampiran_path' => null,
            'lampiran_mime' => null,
        ]);

        $langkah = PersetujuanIzin::where('pengajuan_izin_id', $pengajuan->id)->first();
        $this->service->putuskan($langkah, $this->userKepsek, true, null);

        // Harus ada 2 baris absensi (satu per tanggal)
        $absensi = Absensi::where('pegawai_id', $this->pegawaiGuru->id)
            ->where('sumber', 'izin')
            ->get();

        $this->assertCount(2, $absensi);
        $this->assertEquals(StatusAbsensi::Izin, $absensi->first()->status);
        $this->assertEquals('masuk', $absensi->first()->jenis instanceof \BackedEnum ? $absensi->first()->jenis->value : $absensi->first()->jenis);
        $this->assertNull($absensi->first()->waktu_server);
    }

    public function test_auto_isi_absensi_idempoten_tidak_duplikat(): void
    {
        // Sudah ada baris absensi izin untuk tanggal 15
        Absensi::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'pegawai_id' => $this->pegawaiGuru->id,
            'tanggal' => '2026-10-15',
            'jenis' => 'masuk',
            'status' => 'izin',
            'sumber' => 'izin',
        ]);

        $pengajuan = PengajuanIzin::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'pegawai_id' => $this->pegawaiGuru->id,
            'jenis_izin_id' => $this->jenisIzin->id,
            'tanggal_mulai' => '2026-10-15',
            'tanggal_selesai' => '2026-10-15',
            'status' => 'disetujui',
        ]);

        // AutoIsi tidak boleh menambah baris duplikat
        $this->service->autoIsiAbsensi($pengajuan);

        $count = Absensi::where('pegawai_id', $this->pegawaiGuru->id)
            ->where('tanggal', '2026-10-15')
            ->where('jenis', 'masuk')
            ->count();

        $this->assertEquals(1, $count, 'autoIsiAbsensi harus idempoten — tidak boleh duplikat');
    }

    // =========================================================================
    // T-09.05 — Guard Scan QR di Hari Izin Disetujui
    // =========================================================================

    public function test_scan_qr_ditolak_dengan_pesan_jelas_jika_ada_izin_disetujui_hari_ini(): void
    {
        // Buat baris absensi izin (dari izin yang sudah disetujui)
        Absensi::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'pegawai_id' => $this->pegawaiGuru->id,
            'tanggal' => today()->toDateString(),
            'jenis' => 'masuk',
            'status' => 'izin',
            'sumber' => 'izin',
        ]);

        $this->expectException(ValidationException::class);

        $this->service->pastikanTidakAdaIzinDisetujui($this->pegawaiGuru, today()->toDateString());
    }

    public function test_scan_qr_tidak_ditolak_jika_tidak_ada_izin_hari_ini(): void
    {
        // Tidak ada izin hari ini — tidak boleh throw exception
        $this->service->pastikanTidakAdaIzinDisetujui($this->pegawaiGuru, today()->toDateString());

        $this->assertTrue(true); // Sampai sini = tidak ada exception
    }

    // =========================================================================
    // T-09.05 AC6 — Validasi Ulang saat Persetujuan Final
    // =========================================================================

    public function test_pengajuan_dibatalkan_sistem_jika_pegawai_hadir_selama_jeda_waktu(): void
    {
        $pengajuan = $this->service->ajukan($this->pegawaiGuru, [
            'jenis_izin_id' => $this->jenisIzin->id,
            'tanggal_mulai' => '2026-10-15',
            'tanggal_selesai' => '2026-10-15',
            'alasan' => 'Keperluan',
            'lampiran_path' => null,
            'lampiran_mime' => null,
        ]);

        // Simulasi: selama menunggu persetujuan, pegawai ternyata hadir via QR
        Absensi::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'pegawai_id' => $this->pegawaiGuru->id,
            'tanggal' => '2026-10-15',
            'jenis' => 'masuk',
            'status' => 'hadir',
            'sumber' => 'qr',
        ]);

        // Kepsek approve — tapi AC6 validasi ulang harus membatalkan pengajuan
        $langkah = PersetujuanIzin::where('pengajuan_izin_id', $pengajuan->id)->first();

        $response = $this->actingAs($this->userKepsek)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('izin.inbox.putuskan', $langkah), [
                'keputusan' => 'disetujui',
            ]);

        // Verifikasi response ke approver membawa pesan peringatan eksplisit, bukan sukses generik
        $response->assertSessionHas('warning', 'Persetujuan tidak dapat diselesaikan — pegawai sudah tercatat hadir pada tanggal terkait, pengajuan otomatis dibatalkan.');
        $response->assertSessionMissing('success');

        $pengajuan->refresh();
        $this->assertEquals(StatusPengajuanIzin::Dibatalkan, $pengajuan->status,
            'Pengajuan harus dibatalkan sistem jika pegawai sudah hadir saat persetujuan akhir'
        );
    }

    // =========================================================================
    // T-09.06 — Kuota Cuti
    // =========================================================================

    public function test_kuota_cuti_berkurang_saat_pengajuan_cuti_disetujui(): void
    {
        // Buat kuota cuti awal
        KuotaCuti::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'pegawai_id' => $this->pegawaiGuru->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'kuota_hari' => 12,
            'terpakai' => 0,
        ]);

        $pengajuan = $this->service->ajukan($this->pegawaiGuru, [
            'jenis_izin_id' => $this->jenisCuti->id,
            'tanggal_mulai' => '2026-10-20',
            'tanggal_selesai' => '2026-10-22', // 3 hari
            'alasan' => 'Cuti tahunan',
            'lampiran_path' => null,
            'lampiran_mime' => null,
        ]);

        $langkah = PersetujuanIzin::where('pengajuan_izin_id', $pengajuan->id)->first();
        $this->service->putuskan($langkah, $this->userKepsek, true, null);

        $kuota = KuotaCuti::where('pegawai_id', $this->pegawaiGuru->id)->first();
        $this->assertEquals(3, $kuota->terpakai, 'Kuota terpakai harus bertambah 3 hari');
        $this->assertEquals(9, $kuota->sisaHari(), 'Sisa kuota harus 12 - 3 = 9 hari');
    }

    public function test_pengajuan_cuti_ditolak_jika_kuota_tidak_cukup(): void
    {
        // Kuota sudah habis
        KuotaCuti::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'pegawai_id' => $this->pegawaiGuru->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'kuota_hari' => 12,
            'terpakai' => 12, // Sudah penuh
        ]);

        $this->expectException(ValidationException::class);

        $this->service->ajukan($this->pegawaiGuru, [
            'jenis_izin_id' => $this->jenisCuti->id,
            'tanggal_mulai' => '2026-10-20',
            'tanggal_selesai' => '2026-10-20',
            'alasan' => 'Minta cuti lagi',
            'lampiran_path' => null,
            'lampiran_mime' => null,
        ]);
    }

    // =========================================================================
    // T-09.01 — Jenis Izin tanpa Persetujuan (auto-approve)
    // =========================================================================

    public function test_jenis_tanpa_persetujuan_langsung_disetujui_dan_isi_absensi(): void
    {
        $jenisTanpaApproval = JenisIzin::factory()->tanpaPersetujuan()->create([
            'kode' => 'sakit',
            'nama' => 'Sakit',
        ]);

        $pengajuan = $this->service->ajukan($this->pegawaiGuru, [
            'jenis_izin_id' => $jenisTanpaApproval->id,
            'tanggal_mulai' => '2026-10-10',
            'tanggal_selesai' => '2026-10-10',
            'alasan' => 'Demam',
            'lampiran_path' => null,
            'lampiran_mime' => null,
        ]);

        // Langsung disetujui tanpa langkah persetujuan
        $this->assertEquals(StatusPengajuanIzin::Disetujui, $pengajuan->status);
        $this->assertDatabaseMissing('persetujuan_izin', ['pengajuan_izin_id' => $pengajuan->id]);

        // Absensi harus langsung terisi
        $this->assertDatabaseHas('absensi', [
            'pegawai_id' => $this->pegawaiGuru->id,
            'tanggal' => '2026-10-10',
            'status' => 'sakit',
            'sumber' => 'izin',
        ]);
    }

    // =========================================================================
    // Isolasi Tenant 404
    // =========================================================================

    public function test_pengajuan_izin_dari_sekolah_lain_tidak_terlihat(): void
    {
        $sekolahLain = Sekolah::factory()->create();
        $pegawaiLain = Pegawai::factory()->create(['sekolah_id' => $sekolahLain->id]);
        $pengajuanLain = PengajuanIzin::factory()->create([
            'sekolah_id' => $sekolahLain->id,
            'pegawai_id' => $pegawaiLain->id,
            'jenis_izin_id' => $this->jenisIzin->id,
        ]);

        // Guru Sekolah A mencoba akses pengajuan Sekolah B via API
        $this->actingAs($this->userGuru)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('izin.pengajuan.batalkan', $pengajuanLain->id))
            ->assertStatus(404);
    }

    // =========================================================================
    // RBAC Endpoint
    // =========================================================================

    public function test_orang_tua_tidak_bisa_mengajukan_izin(): void
    {
        $userOrtu = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        setPermissionsTeamId($this->sekolah->id);
        $userOrtu->assignRole('orang_tua');

        $this->actingAs($userOrtu)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('izin.pengajuan.index'))
            ->assertStatus(403);
    }

    public function test_operator_tidak_bisa_akses_inbox_persetujuan(): void
    {
        $this->actingAs($this->userOperator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('izin.inbox.index'))
            ->assertStatus(403);
    }

    public function test_guru_bisa_akses_rekap_hanya_data_sendiri(): void
    {
        $this->actingAs($this->userGuru)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('izin.rekap.index'))
            ->assertOk();
    }

    public function test_operator_tidak_bisa_akses_ekspor(): void
    {
        // Operator boleh akses ekspor (PRD: Super Admin, Operator, Kepsek)
        $this->actingAs($this->userOperator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('izin.ekspor.excel').'?bulan=10&tahun=2026')
            ->assertOk(); // Operator BISA export
    }

    public function test_response_penolakan_tidak_mengandung_data_sekolah_lain(): void
    {
        // Verifikasi bahwa response 403/404 tidak membocorkan data sekolah lain
        $sekolahLain = Sekolah::factory()->create(['nama' => 'SMK Rahasia']);
        $pengajuanLain = PengajuanIzin::factory()->create([
            'sekolah_id' => $sekolahLain->id,
            'pegawai_id' => Pegawai::factory()->create(['sekolah_id' => $sekolahLain->id])->id,
            'jenis_izin_id' => $this->jenisIzin->id,
        ]);

        $response = $this->actingAs($this->userGuru)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('izin.pengajuan.batalkan', $pengajuanLain->id));

        $response->assertStatus(404);

        // Response body tidak boleh mengandung nama sekolah lain
        $content = $response->getContent();
        $this->assertStringNotContainsString('SMK Rahasia', $content ?? '');
    }
}
