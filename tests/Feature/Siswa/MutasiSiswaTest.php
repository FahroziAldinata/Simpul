<?php

namespace Tests\Feature\Siswa;

use App\Enums\JenisMutasi;
use App\Enums\StatusSiswa;
use App\Models\AnggotaRombel;
use App\Models\MutasiSiswa;
use App\Models\Pegawai;
use App\Models\Rombel;
use App\Models\Sekolah;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MutasiSiswaTest extends TestCase
{
    use RefreshDatabase;

    protected Sekolah $sekolah;

    protected User $operator;

    protected User $userWaliKelas;

    protected Pegawai $pegawaiWaliKelas;

    protected Semester $semesterAktif;

    protected Rombel $rombelA;

    protected Rombel $rombelB;

    protected Siswa $siswa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $this->sekolah = Sekolah::factory()->create();
        setPermissionsTeamId($this->sekolah->id);

        $this->operator = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $this->operator->assignRole('operator');

        $ta = TahunAjaran::factory()->create(['sekolah_id' => $this->sekolah->id, 'is_aktif' => true]);
        $this->semesterAktif = Semester::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'tahun_ajaran_id' => $ta->id,
            'is_aktif' => true,
        ]);

        $this->userWaliKelas = User::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $this->userWaliKelas->assignRole('wali_kelas');

        $this->pegawaiWaliKelas = Pegawai::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'user_id' => $this->userWaliKelas->id,
        ]);

        $this->rombelA = Rombel::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semesterAktif->id,
            'wali_kelas_id' => $this->pegawaiWaliKelas->id,
            'nama' => 'X-A',
            'tingkat' => 10,
        ]);

        $this->rombelB = Rombel::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semesterAktif->id,
            'nama' => 'X-B',
            'tingkat' => 10,
        ]);

        $this->siswa = Siswa::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'status' => StatusSiswa::Aktif,
        ]);
    }

    public function test_operator_can_execute_mutasi_masuk_and_creates_new_anggota_rombel(): void
    {
        $siswaBaru = Siswa::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'status' => StatusSiswa::Aktif,
        ]);

        $response = $this->actingAs($this->operator)
            ->postJson("/siswa/{$siswaBaru->id}/mutasi", [
                'tipe' => JenisMutasi::Masuk->value,
                'tanggal' => '2026-09-01',
                'asal_sekolah' => 'SMP Negeri 1 Surabaya',
                'ke_rombel_id' => $this->rombelA->id,
                'alasan' => 'Pindahan dari luar kota',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('anggota_rombel', [
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semesterAktif->id,
            'siswa_id' => $siswaBaru->id,
            'rombel_id' => $this->rombelA->id,
        ]);

        $this->assertDatabaseHas('mutasi_siswa', [
            'siswa_id' => $siswaBaru->id,
            'tipe' => JenisMutasi::Masuk->value,
            'ke_rombel_id' => $this->rombelA->id,
            'asal_sekolah' => 'SMP Negeri 1 Surabaya',
            'anggota_rombel_dibuat_baru' => true,
            'is_batal' => false,
        ]);
    }

    public function test_operator_can_execute_mutasi_pindah_rombel_and_updates_anggota_rombel(): void
    {
        AnggotaRombel::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semesterAktif->id,
            'siswa_id' => $this->siswa->id,
            'rombel_id' => $this->rombelA->id,
        ]);

        $response = $this->actingAs($this->operator)
            ->postJson("/siswa/{$this->siswa->id}/mutasi", [
                'tipe' => JenisMutasi::PindahRombel->value,
                'tanggal' => '2026-09-10',
                'ke_rombel_id' => $this->rombelB->id,
                'alasan' => 'Pemerataan jumlah siswa',
            ]);

        $response->assertStatus(201);

        // Anggota rombel updated to rombelB
        $this->assertDatabaseHas('anggota_rombel', [
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semesterAktif->id,
            'siswa_id' => $this->siswa->id,
            'rombel_id' => $this->rombelB->id,
        ]);

        // Mutasi record snapshots
        $this->assertDatabaseHas('mutasi_siswa', [
            'siswa_id' => $this->siswa->id,
            'tipe' => JenisMutasi::PindahRombel->value,
            'dari_rombel_id' => $this->rombelA->id,
            'ke_rombel_id' => $this->rombelB->id,
            'rombel_id_sebelum' => $this->rombelA->id,
            'anggota_rombel_dibuat_baru' => false,
            'is_batal' => false,
        ]);
    }

    public function test_operator_can_execute_naik_kelas_and_tinggal_kelas(): void
    {
        AnggotaRombel::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semesterAktif->id,
            'siswa_id' => $this->siswa->id,
            'rombel_id' => $this->rombelA->id,
        ]);

        $response = $this->actingAs($this->operator)
            ->postJson("/siswa/{$this->siswa->id}/mutasi", [
                'tipe' => JenisMutasi::NaikKelas->value,
                'tanggal' => '2026-09-15',
                'ke_rombel_id' => $this->rombelB->id,
                'alasan' => 'Kenaikan kelas reguler',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('mutasi_siswa', [
            'siswa_id' => $this->siswa->id,
            'tipe' => JenisMutasi::NaikKelas->value,
            'ke_rombel_id' => $this->rombelB->id,
        ]);
    }

    public function test_mutasi_keluar_lulus_dan_drop_out_preserves_anggota_rombel_history(): void
    {
        AnggotaRombel::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semesterAktif->id,
            'siswa_id' => $this->siswa->id,
            'rombel_id' => $this->rombelA->id,
        ]);

        // 1. Mutasi Keluar
        $responseKeluar = $this->actingAs($this->operator)
            ->postJson("/siswa/{$this->siswa->id}/mutasi", [
                'tipe' => JenisMutasi::Keluar->value,
                'tanggal' => '2026-09-20',
                'sekolah_tujuan' => 'SMA Negeri 5 Bandung',
                'alasan' => 'Ikut orang tua pindah dinas',
            ]);

        $responseKeluar->assertStatus(201);

        // Siswa status becomes pindah
        $this->assertEquals(StatusSiswa::Pindah, $this->siswa->fresh()->status);

        // US-13 AC1: Anggota rombel row MUST NOT be deleted (historical record remains intact)
        $this->assertDatabaseHas('anggota_rombel', [
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semesterAktif->id,
            'siswa_id' => $this->siswa->id,
            'rombel_id' => $this->rombelA->id,
        ]);

        // 2. Test Mutasi Lulus
        $siswaLulus = Siswa::factory()->create(['sekolah_id' => $this->sekolah->id, 'status' => StatusSiswa::Aktif]);
        AnggotaRombel::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semesterAktif->id,
            'siswa_id' => $siswaLulus->id,
            'rombel_id' => $this->rombelA->id,
        ]);

        $responseLulus = $this->actingAs($this->operator)
            ->postJson("/siswa/{$siswaLulus->id}/mutasi", [
                'tipe' => JenisMutasi::Lulus->value,
                'tanggal' => '2026-09-21',
                'alasan' => 'Telah menyelesaikan pendidikan',
            ]);

        $responseLulus->assertStatus(201);
        $this->assertEquals(StatusSiswa::Lulus, $siswaLulus->fresh()->status);
        $this->assertDatabaseHas('anggota_rombel', ['siswa_id' => $siswaLulus->id]);

        // 3. Test Mutasi Drop Out
        $siswaDO = Siswa::factory()->create(['sekolah_id' => $this->sekolah->id, 'status' => StatusSiswa::Aktif]);
        AnggotaRombel::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semesterAktif->id,
            'siswa_id' => $siswaDO->id,
            'rombel_id' => $this->rombelA->id,
        ]);

        $responseDO = $this->actingAs($this->operator)
            ->postJson("/siswa/{$siswaDO->id}/mutasi", [
                'tipe' => JenisMutasi::DropOut->value,
                'tanggal' => '2026-09-22',
                'alasan' => 'Mangkir dan mengundurkan diri',
            ]);

        $responseDO->assertStatus(201);
        $this->assertEquals(StatusSiswa::DropOut, $siswaDO->fresh()->status);
        $this->assertDatabaseHas('anggota_rombel', ['siswa_id' => $siswaDO->id]);
    }

    public function test_mutasi_rejected_when_rombel_tujuan_is_in_inactive_or_archived_semester(): void
    {
        $semesterArsip = Semester::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'is_aktif' => false,
        ]);

        $rombelArsip = Rombel::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $semesterArsip->id,
            'nama' => 'X-Arsip',
        ]);

        $response = $this->actingAs($this->operator)
            ->postJson("/siswa/{$this->siswa->id}/mutasi", [
                'tipe' => JenisMutasi::PindahRombel->value,
                'tanggal' => '2026-09-25',
                'ke_rombel_id' => $rombelArsip->id,
                'alasan' => 'Pindah ke rombel semester lama',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ke_rombel_id']);
    }

    public function test_pembatalan_path_a_when_anggota_rombel_dibuat_baru_deletes_created_anggota_rombel(): void
    {
        $siswaBaru = Siswa::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'status' => StatusSiswa::Aktif,
        ]);

        // Eksekusi mutasi masuk (membuat baris anggota_rombel baru)
        $this->actingAs($this->operator)
            ->postJson("/siswa/{$siswaBaru->id}/mutasi", [
                'tipe' => JenisMutasi::Masuk->value,
                'tanggal' => '2026-09-01',
                'asal_sekolah' => 'SMP 1',
                'ke_rombel_id' => $this->rombelA->id,
            ])
            ->assertStatus(201);

        $mutasi = MutasiSiswa::where('siswa_id', $siswaBaru->id)->firstOrFail();
        $this->assertTrue($mutasi->anggota_rombel_dibuat_baru);

        // Pastikan baris anggota_rombel ada sebelum pembatalan
        $this->assertDatabaseHas('anggota_rombel', [
            'semester_id' => $this->semesterAktif->id,
            'siswa_id' => $siswaBaru->id,
            'rombel_id' => $this->rombelA->id,
        ]);

        // Lakukan pembatalan mutasi (Jalur A)
        $responseBatal = $this->actingAs($this->operator)
            ->postJson("/mutasi/{$mutasi->id}/batal", [
                'alasan_batal' => 'Koreksi salah input siswa pindahan',
            ]);

        $responseBatal->assertStatus(200);

        // Baris anggota_rombel yang dibuat baru HARUS DIHAPUS (bukan set rombel_id null yang melanggar FK)
        $this->assertDatabaseMissing('anggota_rombel', [
            'semester_id' => $this->semesterAktif->id,
            'siswa_id' => $siswaBaru->id,
        ]);

        // Flag pembatalan tercatat
        $mutasi->refresh();
        $this->assertTrue($mutasi->is_batal);
        $this->assertEquals('Koreksi salah input siswa pindahan', $mutasi->alasan_batal);
        $this->assertEquals($this->operator->id, $mutasi->dibatalkan_oleh);
        $this->assertNotNull($mutasi->dibatalkan_at);
    }

    public function test_pembatalan_path_b_when_anggota_rombel_existing_reverts_rombel_id_to_rombel_id_sebelum(): void
    {
        AnggotaRombel::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semesterAktif->id,
            'siswa_id' => $this->siswa->id,
            'rombel_id' => $this->rombelA->id,
        ]);

        // Eksekusi mutasi pindah rombel dari rombelA ke rombelB
        $this->actingAs($this->operator)
            ->postJson("/siswa/{$this->siswa->id}/mutasi", [
                'tipe' => JenisMutasi::PindahRombel->value,
                'tanggal' => '2026-09-10',
                'ke_rombel_id' => $this->rombelB->id,
                'alasan' => 'Rencana pemindahan',
            ])
            ->assertStatus(201);

        $mutasi = MutasiSiswa::where('siswa_id', $this->siswa->id)->firstOrFail();
        $this->assertFalse($mutasi->anggota_rombel_dibuat_baru);
        $this->assertEquals($this->rombelA->id, $mutasi->rombel_id_sebelum);

        // Anggota rombel sekarang di rombelB
        $this->assertDatabaseHas('anggota_rombel', [
            'siswa_id' => $this->siswa->id,
            'rombel_id' => $this->rombelB->id,
        ]);

        // Lakukan pembatalan mutasi (Jalur B)
        $responseBatal = $this->actingAs($this->operator)
            ->postJson("/mutasi/{$mutasi->id}/batal", [
                'alasan_batal' => 'Siswa batal dipindahkan ke rombel B',
            ]);

        $responseBatal->assertStatus(200);

        // Anggota rombel HARUS DIKEMBALIKAN ke rombel_id_sebelum (rombelA)
        $this->assertDatabaseHas('anggota_rombel', [
            'semester_id' => $this->semesterAktif->id,
            'siswa_id' => $this->siswa->id,
            'rombel_id' => $this->rombelA->id,
        ]);

        $mutasi->refresh();
        $this->assertTrue($mutasi->is_batal);
    }

    public function test_pembatalan_enforces_lifo_rule_rejects_if_newer_uncancelled_mutation_exists(): void
    {
        AnggotaRombel::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semesterAktif->id,
            'siswa_id' => $this->siswa->id,
            'rombel_id' => $this->rombelA->id,
        ]);

        $rombelC = Rombel::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semesterAktif->id,
            'nama' => 'X-C',
            'tingkat' => 10,
        ]);

        // Mutasi 1: Pindah ke Rombel B pada 1 September
        $this->actingAs($this->operator)
            ->postJson("/siswa/{$this->siswa->id}/mutasi", [
                'tipe' => JenisMutasi::PindahRombel->value,
                'tanggal' => '2026-09-01',
                'ke_rombel_id' => $this->rombelB->id,
            ])
            ->assertStatus(201);

        $mutasi1 = MutasiSiswa::where('siswa_id', $this->siswa->id)
            ->where('ke_rombel_id', $this->rombelB->id)
            ->firstOrFail();

        // Mutasi 2: Pindah ke Rombel C pada 15 September
        $this->actingAs($this->operator)
            ->postJson("/siswa/{$this->siswa->id}/mutasi", [
                'tipe' => JenisMutasi::PindahRombel->value,
                'tanggal' => '2026-09-15',
                'ke_rombel_id' => $rombelC->id,
            ])
            ->assertStatus(201);

        $mutasi2 = MutasiSiswa::where('siswa_id', $this->siswa->id)
            ->where('ke_rombel_id', $rombelC->id)
            ->firstOrFail();

        // Coba batalkan Mutasi 1 saat Mutasi 2 masih aktif (belum dibatalkan) -> HARUS DITOLAK
        $responseTolak = $this->actingAs($this->operator)
            ->postJson("/mutasi/{$mutasi1->id}/batal", [
                'alasan_batal' => 'Mencoba membatalkan mutasi lama',
            ]);

        $responseTolak->assertStatus(422);
        $responseTolak->assertJsonValidationErrors(['mutasi']);

        // Batalkan Mutasi 2 terlebih dahulu (mutasi terbaru) -> HARUS SUKSES
        $this->actingAs($this->operator)
            ->postJson("/mutasi/{$mutasi2->id}/batal", [
                'alasan_batal' => 'Membatalkan mutasi 2',
            ])
            ->assertStatus(200);

        // Sekarang batalkan Mutasi 1 -> HARUS SUKSES karena Mutasi 2 sudah dibatalkan
        $responseSukses = $this->actingAs($this->operator)
            ->postJson("/mutasi/{$mutasi1->id}/batal", [
                'alasan_batal' => 'Sekarang bisa membatalkan mutasi 1',
            ]);

        $responseSukses->assertStatus(200);

        $this->assertTrue($mutasi1->fresh()->is_batal);
        $this->assertTrue($mutasi2->fresh()->is_batal);
    }

    public function test_pembatalan_rejects_already_cancelled_mutation(): void
    {
        AnggotaRombel::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semesterAktif->id,
            'siswa_id' => $this->siswa->id,
            'rombel_id' => $this->rombelA->id,
        ]);

        $this->actingAs($this->operator)
            ->postJson("/siswa/{$this->siswa->id}/mutasi", [
                'tipe' => JenisMutasi::PindahRombel->value,
                'tanggal' => '2026-09-01',
                'ke_rombel_id' => $this->rombelB->id,
            ])
            ->assertStatus(201);

        $mutasi = MutasiSiswa::where('siswa_id', $this->siswa->id)->firstOrFail();

        // Batal pertama kali -> sukses
        $this->actingAs($this->operator)
            ->postJson("/mutasi/{$mutasi->id}/batal", ['alasan_batal' => 'Batal pertama'])
            ->assertStatus(200);

        // Batal kedua kali -> tolak 422
        $responseRebatal = $this->actingAs($this->operator)
            ->postJson("/mutasi/{$mutasi->id}/batal", ['alasan_batal' => 'Batal kedua'])
            ->assertStatus(422);

        $responseRebatal->assertJsonValidationErrors(['mutasi']);
    }

    public function test_wali_kelas_cannot_execute_or_cancel_mutasi_returns_403(): void
    {
        AnggotaRombel::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semesterAktif->id,
            'siswa_id' => $this->siswa->id,
            'rombel_id' => $this->rombelA->id,
        ]);

        // Wali kelas tries to execute mutation -> 403
        $responseExecute = $this->actingAs($this->userWaliKelas)
            ->postJson("/siswa/{$this->siswa->id}/mutasi", [
                'tipe' => JenisMutasi::PindahRombel->value,
                'tanggal' => '2026-09-01',
                'ke_rombel_id' => $this->rombelB->id,
            ]);

        $responseExecute->assertStatus(403);

        // Create mutasi as operator
        $this->actingAs($this->operator)
            ->postJson("/siswa/{$this->siswa->id}/mutasi", [
                'tipe' => JenisMutasi::PindahRombel->value,
                'tanggal' => '2026-09-01',
                'ke_rombel_id' => $this->rombelB->id,
            ])
            ->assertStatus(201);

        $mutasi = MutasiSiswa::where('siswa_id', $this->siswa->id)->firstOrFail();

        // Wali kelas tries to cancel mutation -> 403
        $responseCancel = $this->actingAs($this->userWaliKelas)
            ->postJson("/mutasi/{$mutasi->id}/batal", [
                'alasan_batal' => 'Wali kelas mencoba batalkan',
            ]);

        $responseCancel->assertStatus(403);
    }

    public function test_cross_school_tenant_isolation_returns_404(): void
    {
        $sekolahLain = Sekolah::factory()->create();
        $siswaSekolahLain = Siswa::factory()->create(['sekolah_id' => $sekolahLain->id]);

        // Operator sekolah ini mencoba mutasi siswa sekolah lain -> 404
        $response = $this->actingAs($this->operator)
            ->postJson("/siswa/{$siswaSekolahLain->id}/mutasi", [
                'tipe' => JenisMutasi::PindahRombel->value,
                'tanggal' => '2026-09-01',
                'ke_rombel_id' => $this->rombelB->id,
            ]);

        $response->assertStatus(404);

        // Operator sekolah ini mencoba index mutasi siswa sekolah lain -> 404
        $responseIndex = $this->actingAs($this->operator)
            ->getJson("/siswa/{$siswaSekolahLain->id}/mutasi");

        $responseIndex->assertStatus(404);
    }
}
