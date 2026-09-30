<?php

namespace Tests\Feature\Siswa;

use App\Enums\JenisMutasi;
use App\Enums\StatusSiswa;
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

    public function test_nisn_conflict_when_mutating_out_from_school_b_and_registering_in_school_a(): void
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

        // Jalankan mutasi keluar di Sekolah B (status jadi pindah, tidak di-soft-delete)
        $mutasiService = app(MutasiService::class);
        $mutasiService->executeMutasi(
            siswa: $siswaB,
            tipe: JenisMutasi::Keluar,
            tanggal: now()->toDateString(),
            alasan: 'Pindah ke Sekolah A',
            sekolahTujuan: 'Sekolah A'
        );

        $siswaB->refresh();
        expect($siswaB->status)->toBe(StatusSiswa::Pindah);
        expect($siswaB->deleted_at)->toBeNull();

        // Sekolah A
        $sekolahA = Sekolah::factory()->create(['nama' => 'Sekolah A']);
        setPermissionsTeamId($sekolahA->id);
        $operatorA = User::factory()->create(['sekolah_id' => $sekolahA->id]);
        $operatorA->assignRole('operator');

        // Coba daftarkan siswa dengan NISN sama di Sekolah A
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

        // Cek apakah gagal validasi unique NISN
        $response->assertSessionHasErrors(['nisn']);
    }
}
