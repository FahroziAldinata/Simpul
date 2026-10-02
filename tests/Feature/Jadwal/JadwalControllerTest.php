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
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JadwalControllerTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $sekolah;

    private Sekolah $sekolahLain;

    private Semester $semester;

    private Pegawai $guru1;

    private Pegawai $guru2;

    private Ruang $ruang1;

    private Ruang $ruang2;

    private Rombel $rombel1;

    private Rombel $rombel2;

    private MataPelajaran $mapel1;

    private MataPelajaran $mapel2;

    private User $userWaka;

    private User $userGuru;

    private User $userSuperAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->sekolah = Sekolah::factory()->create();
        $this->sekolahLain = Sekolah::factory()->create();

        $this->semester = Semester::factory()->create(['sekolah_id' => $this->sekolah->id, 'is_aktif' => true]);

        // Setup jam operasional (H5)
        for ($h = 1; $h <= 5; $h++) {
            JamKerja::create([
                'sekolah_id' => $this->sekolah->id,
                'kelompok' => 'umum',
                'hari' => $h,
                'is_libur' => false,
                'jumlah_jam_pelajaran' => $h === 5 ? 6 : 10,
            ]);
        }
        JamKerja::create([
            'sekolah_id' => $this->sekolah->id,
            'kelompok' => 'umum',
            'hari' => 6,
            'is_libur' => true,
            'jumlah_jam_pelajaran' => 0,
        ]);

        $this->guru1 = Pegawai::factory()->create(['sekolah_id' => $this->sekolah->id, 'nama' => 'Budi Santoso, S.Pd.']);
        $this->guru2 = Pegawai::factory()->create(['sekolah_id' => $this->sekolah->id, 'nama' => 'Siti Aminah, M.Kom.']);
        $this->ruang1 = Ruang::factory()->create(['sekolah_id' => $this->sekolah->id, 'nama' => 'Lab Komputer 1', 'kategori' => 'laboratorium']);
        $this->ruang2 = Ruang::factory()->create(['sekolah_id' => $this->sekolah->id, 'nama' => 'Ruang Teori 101', 'kategori' => 'teori']);
        $this->rombel1 = Rombel::factory()->create(['sekolah_id' => $this->sekolah->id, 'semester_id' => $this->semester->id, 'nama' => 'X RPL 1']);
        $this->rombel2 = Rombel::factory()->create(['sekolah_id' => $this->sekolah->id, 'semester_id' => $this->semester->id, 'nama' => 'XI TKJ 2']);
        $this->mapel1 = MataPelajaran::factory()->create(['sekolah_id' => $this->sekolah->id, 'nama' => 'Pemrograman Web', 'butuh_ruang_kategori' => null]);
        $this->mapel2 = MataPelajaran::factory()->create(['sekolah_id' => $this->sekolah->id, 'nama' => 'Basis Data', 'butuh_ruang_kategori' => null]);

        // Alokasi jam mapel (H4)
        AlokasiJamMapel::create([
            'sekolah_id' => $this->sekolah->id,
            'rombel_id' => $this->rombel1->id,
            'mata_pelajaran_id' => $this->mapel1->id,
            'jam_per_minggu' => 4,
        ]);
        AlokasiJamMapel::create([
            'sekolah_id' => $this->sekolah->id,
            'rombel_id' => $this->rombel1->id,
            'mata_pelajaran_id' => $this->mapel2->id,
            'jam_per_minggu' => 3,
        ]);

        // Users & Roles
        setPermissionsTeamId($this->sekolah->id);

        $this->userWaka = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $this->userWaka->assignRole('waka_kurikulum');

        $this->userGuru = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $this->userGuru->assignRole('guru');

        $this->userSuperAdmin = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $this->userSuperAdmin->assignRole('super_admin');
    }

    /**
     * T-11.06: Waka Kurikulum dapat membuka halaman index jadwal pelajaran.
     */
    public function test_waka_kurikulum_bisa_melihat_halaman_jadwal(): void
    {
        $response = $this->actingAs($this->userWaka)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('jadwal.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('jadwal/Index')
            ->has('semesters')
            ->has('rombels')
            ->has('gurus')
            ->has('ruangs')
            ->has('mapels')
            ->has('jamKerja')
            ->has('jadwals')
            ->has('ringkasan')
            ->where('canManage', true)
        );
    }

    /**
     * RBAC: Guru hanya memiliki akses baca (canManage false), user tanpa izin ditolak.
     */
    public function test_peran_guru_hanya_punya_akses_baca(): void
    {
        $response = $this->actingAs($this->userGuru)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('jadwal.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('jadwal/Index')
            ->where('canManage', false)
        );

        // Guru coba membuat jadwal -> 403
        $createResponse = $this->actingAs($this->userGuru)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('jadwal.store'), [
                'semester_id' => $this->semester->id,
                'rombel_id' => $this->rombel1->id,
                'mata_pelajaran_id' => $this->mapel1->id,
                'guru_id' => $this->guru1->id,
                'hari' => 1,
                'jam_mulai_ke' => 1,
                'jam_selesai_ke' => 3,
            ]);

        $createResponse->assertForbidden();
    }

    /**
     * T-11.05 & Store: Waka Kurikulum dapat menambahkan jadwal valid baru.
     */
    public function test_waka_kurikulum_bisa_menambah_jadwal_valid(): void
    {
        $response = $this->actingAs($this->userWaka)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('jadwal.store'), [
                'semester_id' => $this->semester->id,
                'rombel_id' => $this->rombel1->id,
                'mata_pelajaran_id' => $this->mapel1->id,
                'guru_id' => $this->guru1->id,
                'ruang_id' => $this->ruang1->id,
                'hari' => 1,
                'jam_mulai_ke' => 1,
                'jam_selesai_ke' => 3,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('jadwal_pelajaran', [
            'sekolah_id' => $this->sekolah->id,
            'rombel_id' => $this->rombel1->id,
            'guru_id' => $this->guru1->id,
            'hari' => 1,
            'jam_mulai_ke' => 1,
            'jam_selesai_ke' => 3,
        ]);
    }

    /**
     * T-11.04 & T-11.05: Penambahan jadwal bentrok ditolak oleh ConflictDetector dengan pesan spesifik.
     */
    public function test_penyimpanan_jadwal_bentrok_ditolak_dengan_pesan_spesifik(): void
    {
        // Buat jadwal awal: Guru 1 mengajar Senin jam 1..3
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

        // Coba jadwalkan Guru 1 di rombel lain pada jam yang overlap
        $response = $this->actingAs($this->userWaka)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('jadwal.store'), [
                'semester_id' => $this->semester->id,
                'rombel_id' => $this->rombel2->id,
                'mata_pelajaran_id' => $this->mapel2->id,
                'guru_id' => $this->guru1->id,
                'ruang_id' => $this->ruang2->id,
                'hari' => 1,
                'jam_mulai_ke' => 2,
                'jam_selesai_ke' => 4,
            ]);

        $response->assertSessionHasErrors('conflict');
        $this->assertDatabaseCount('jadwal_pelajaran', 1);
    }

    /**
     * T-11.07: Drag and drop pemindahan slot berhasil jika slot tujuan valid.
     */
    public function test_pemindahan_slot_via_endpoint_move_berhasil_jika_slot_valid(): void
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

        // Pindahkan ke hari Selasa jam 3..5 via AJAX JSON
        $response = $this->actingAs($this->userWaka)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->patchJson(route('jadwal.move', $jadwal), [
                'hari' => 2,
                'jam_mulai_ke' => 3,
                'jam_selesai_ke' => 5,
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Slot jadwal berhasil dipindahkan.',
        ]);

        $this->assertDatabaseHas('jadwal_pelajaran', [
            'id' => $jadwal->id,
            'hari' => 2,
            'jam_mulai_ke' => 3,
            'jam_selesai_ke' => 5,
        ]);
    }

    /**
     * T-11.07 & T-11.08: Pemindahan slot bentrok ditolak dengan kode 422 dan pesan bentrok.
     */
    public function test_pemindahan_slot_via_endpoint_move_ditolak_jika_bentrok(): void
    {
        // Jadwal penghalang: Guru 1 mengajar Selasa jam 3..5 di rombel 1
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

        // Jadwal yang ingin dipindah: Guru 1 mengajar di rombel 2 hari Senin
        $jadwal2 = JadwalPelajaran::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
            'rombel_id' => $this->rombel2->id,
            'mata_pelajaran_id' => $this->mapel2->id,
            'guru_id' => $this->guru1->id,
            'ruang_id' => $this->ruang2->id,
            'hari' => 1,
            'jam_mulai_ke' => 1,
            'jam_selesai_ke' => 3,
        ]);

        // Coba drag jadwal2 ke hari Selasa jam 4..6 (overlap jam 4 dengan jadwal1)
        $response = $this->actingAs($this->userWaka)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->patchJson(route('jadwal.move', $jadwal2), [
                'hari' => 2,
                'jam_mulai_ke' => 4,
                'jam_selesai_ke' => 6,
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);
        $response->assertJsonFragment(['conflict_type' => 'guru']);

        // Posisi jadwal2 tidak berubah di database
        $this->assertDatabaseHas('jadwal_pelajaran', [
            'id' => $jadwal2->id,
            'hari' => 1,
            'jam_mulai_ke' => 1,
        ]);
    }

    /**
     * T-11.09: Tampilan dipertukarkan (data yang sama dari sudut pandang rombel, guru, ruang).
     */
    public function test_tampilan_dipertukarkan_memuat_data_yang_sama(): void
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

        // Request index memuat array jadwal yang mencakup semua relasi (rombel, guru, ruang)
        $response = $this->actingAs($this->userWaka)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('jadwal.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('jadwals', 1)
            ->where('jadwals.0.id', $jadwal->id)
            ->where('jadwals.0.rombel_id', $this->rombel1->id)
            ->where('jadwals.0.guru_id', $this->guru1->id)
            ->where('jadwals.0.ruang_id', $this->ruang1->id)
        );
    }

    /**
     * Isolasi Tenant: Akses / mutasi jadwal sekolah lain mengembalikan 404.
     */
    public function test_isolasi_tenant_404_untuk_jadwal_sekolah_lain(): void
    {
        $semesterSekolahLain = Semester::factory()->create(['sekolah_id' => $this->sekolahLain->id]);
        $rombelLain = Rombel::factory()->create(['sekolah_id' => $this->sekolahLain->id, 'semester_id' => $semesterSekolahLain->id]);
        $guruLain = Pegawai::factory()->create(['sekolah_id' => $this->sekolahLain->id]);
        $mapelLain = MataPelajaran::factory()->create(['sekolah_id' => $this->sekolahLain->id]);

        $jadwalLain = JadwalPelajaran::factory()->create([
            'sekolah_id' => $this->sekolahLain->id,
            'semester_id' => $semesterSekolahLain->id,
            'rombel_id' => $rombelLain->id,
            'mata_pelajaran_id' => $mapelLain->id,
            'guru_id' => $guruLain->id,
            'hari' => 1,
            'jam_mulai_ke' => 1,
            'jam_selesai_ke' => 3,
        ]);

        // Update jadwal sekolah lain -> 404
        $responseUpdate = $this->actingAs($this->userWaka)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->put(route('jadwal.update', $jadwalLain), [
                'semester_id' => $this->semester->id,
                'rombel_id' => $this->rombel1->id,
                'mata_pelajaran_id' => $this->mapel1->id,
                'guru_id' => $this->guru1->id,
                'hari' => 1,
                'jam_mulai_ke' => 1,
                'jam_selesai_ke' => 3,
            ]);

        $responseUpdate->assertNotFound();

        // Move jadwal sekolah lain -> 404
        $responseMove = $this->actingAs($this->userWaka)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->patchJson(route('jadwal.move', $jadwalLain), [
                'hari' => 2,
                'jam_mulai_ke' => 1,
                'jam_selesai_ke' => 3,
            ]);

        $responseMove->assertNotFound();

        // Delete jadwal sekolah lain -> 404
        $responseDelete = $this->actingAs($this->userWaka)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->delete(route('jadwal.destroy', $jadwalLain));

        $responseDelete->assertNotFound();
    }

    /**
     * Penghapusan jadwal menggunakan SoftDeletes.
     */
    public function test_penghapusan_jadwal_melakukan_soft_delete(): void
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

        $response = $this->actingAs($this->userWaka)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->delete(route('jadwal.destroy', $jadwal));

        $response->assertRedirect();
        $this->assertSoftDeleted('jadwal_pelajaran', ['id' => $jadwal->id]);
    }
}
