<?php

namespace Tests\Feature\Siswa;

use App\Enums\JenisMutasi;
use App\Enums\StatusSiswa;
use App\Models\MutasiSiswa;
use App\Models\Sekolah;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\MutasiService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossTenantNisnTest extends TestCase
{
    use RefreshDatabase;

    public function test_nisn_freed_when_mutating_out_from_school_b_and_successfully_registers_in_school_a(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        // Sekolah B
        $sekolahB = Sekolah::factory()->create(['nama' => 'Sekolah B']);
        $taB = TahunAjaran::factory()->create(['sekolah_id' => $sekolahB->id]);
        $semB = Semester::factory()->create([
            'sekolah_id' => $sekolahB->id,
            'tahun_ajaran_id' => $taB->id,
            'is_aktif' => true,
        ]);

        $nisnX = '0087654321';

        $siswaB = Siswa::factory()->create([
            'sekolah_id' => $sekolahB->id,
            'nisn' => $nisnX,
            'status' => StatusSiswa::Aktif,
        ]);

        // Jalankan mutasi keluar di Sekolah B (status jadi pindah dan siswa di-soft-delete untuk membebaskan NISN)
        $mutasiService = app(MutasiService::class);
        $mutasiService->executeMutasi(
            siswa: $siswaB,
            tipe: JenisMutasi::Keluar,
            tanggal: now()->toDateString(),
            alasan: 'Pindah ke Sekolah A',
            sekolahTujuan: 'Sekolah A'
        );

        $refreshedSiswaB = Siswa::withTrashed()->find($siswaB->id);
        $this->assertNotNull($refreshedSiswaB);
        $this->assertEquals(StatusSiswa::Pindah, $refreshedSiswaB->status);
        $this->assertNotNull($refreshedSiswaB->deleted_at);

        // Sekolah A
        $sekolahA = Sekolah::factory()->create(['nama' => 'Sekolah A']);
        setPermissionsTeamId($sekolahA->id);
        $operatorA = User::factory()->create(['sekolah_id' => $sekolahA->id]);
        $operatorA->assignRole('operator');

        // Daftarkan siswa dengan NISN yang sama di Sekolah A (sekarang harus BERHASIL tanpa error validasi)
        $response = $this->actingAs($operatorA)
            ->withSession(['sekolah_id' => $sekolahA->id])
            ->post(route('siswa.store'), [
                'nisn' => $nisnX,
                'nik' => '3201012304080001',
                'nama' => 'Siswa Pindahan',
                'jenis_kelamin' => 'L',
                'tempat_lahir' => 'Jakarta',
                'tanggal_lahir' => '2008-05-17',
                'agama' => 'Islam',
                'status' => 'aktif',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('siswa', [
            'sekolah_id' => $sekolahA->id,
            'nisn' => $nisnX,
            'deleted_at' => null,
        ]);
    }

    public function test_restore_guard_blocks_restoring_soft_deleted_siswa_if_nisn_already_active_in_another_school(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $sekolahB = Sekolah::factory()->create(['nama' => 'Sekolah B']);
        $nisnY = '0098765432';

        $siswaB = Siswa::factory()->create([
            'sekolah_id' => $sekolahB->id,
            'nisn' => $nisnY,
            'status' => StatusSiswa::Pindah,
        ]);
        $siswaB->delete();

        // Sekolah A creates active siswa with same NISN
        $sekolahA = Sekolah::factory()->create(['nama' => 'Sekolah A']);
        Siswa::factory()->create([
            'sekolah_id' => $sekolahA->id,
            'nisn' => $nisnY,
            'status' => StatusSiswa::Aktif,
        ]);

        // Attempting to restore siswaB directly must throw RuntimeException
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Gagal memulihkan data siswa: NISN {$nisnY} sudah aktif digunakan oleh siswa lain.");

        $siswaB->restore();
    }

    public function test_cancel_mutation_rejection_does_not_leak_cross_tenant_school_data(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        // Sekolah B memiliki siswa yang sudah mutasi keluar (soft-deleted)
        $sekolahB = Sekolah::factory()->create(['nama' => 'Sekolah B Rahasia']);
        $taB = TahunAjaran::factory()->create(['sekolah_id' => $sekolahB->id]);
        $semB = Semester::factory()->create([
            'sekolah_id' => $sekolahB->id,
            'tahun_ajaran_id' => $taB->id,
            'is_aktif' => true,
        ]);
        setPermissionsTeamId($sekolahB->id);
        $operatorB = User::factory()->create(['sekolah_id' => $sekolahB->id]);
        $operatorB->assignRole('operator');

        $nisnZ = '0011223344';

        $siswaB = Siswa::factory()->create([
            'sekolah_id' => $sekolahB->id,
            'nisn' => $nisnZ,
            'status' => StatusSiswa::Aktif,
        ]);

        // Jalankan mutasi keluar → siswaB soft-deleted, NISN bebas
        $mutasiRecord = MutasiSiswa::factory()->create([
            'sekolah_id' => $sekolahB->id,
            'siswa_id' => $siswaB->id,
            'semester_id' => $semB->id,
            'tipe' => JenisMutasi::Keluar,
            'status_sebelum' => StatusSiswa::Aktif->value,
            'is_batal' => false,
        ]);
        $siswaB->delete(); // simulasikan kondisi pasca-mutasi keluar

        // Sekolah A mendaftar siswa aktif dengan NISN yang sama
        $sekolahA = Sekolah::factory()->create(['nama' => 'Sekolah A Rahasia']);
        Siswa::factory()->create([
            'sekolah_id' => $sekolahA->id,
            'nisn' => $nisnZ,
            'status' => StatusSiswa::Aktif,
        ]);

        // Operator B mencoba membatalkan mutasi keluar siswaB via HTTP (wantsJson)
        $response = $this->actingAs($operatorB)
            ->withSession(['sekolah_id' => $sekolahB->id])
            ->post(
                route('siswa.mutasi.batal', $mutasiRecord->id),
                ['alasan_batal' => 'Dibatalkan karena salah input'],
                ['Accept' => 'application/json']
            );

        // Harus ditolak (422 Unprocessable Entity)
        $response->assertStatus(422);

        $body = $response->getContent();

        // Assertion privasi: response TIDAK boleh mengandung identitas Sekolah A
        $this->assertStringNotContainsString(
            (string) $sekolahA->id,
            $body,
            'Response tidak boleh menyertakan sekolah_id milik sekolah lain.'
        );
        $this->assertStringNotContainsString(
            'Sekolah A Rahasia',
            $body,
            'Response tidak boleh menyertakan nama sekolah lain.'
        );

        // Pesan harus generik
        $response->assertJsonValidationErrors(['mutasi']);
        $this->assertStringContainsString(
            'tidak bisa dibatalkan',
            $body,
        );
    }
}
