<?php

namespace Tests\Feature\Siswa;

use App\Models\AnggotaRombel;
use App\Models\Rombel;
use App\Models\Sekolah;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\WaliSiswa;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class SiswaCrudTest extends TestCase
{
    use RefreshDatabase;

    protected Sekolah $sekolah;

    protected User $operator;

    protected Semester $semesterAktif;

    protected Rombel $rombel;

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

        $this->rombel = Rombel::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semesterAktif->id,
            'tingkat' => 10,
        ]);
    }

    public function test_operator_can_create_student_with_wali_and_rombel(): void
    {
        $payload = [
            'nisn' => '0012345678',
            'nik' => '3201011205080001',
            'nama' => 'Budi Pratama',
            'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '2008-05-12',
            'agama' => 'Islam',
            'alamat' => 'Jl. Merdeka No. 10',
            'no_hp' => '081234567890',
            'status' => 'aktif',
            'wali' => [
                [
                    'hubungan' => 'ayah',
                    'nama' => 'Herman Pratama',
                    'pekerjaan' => 'Wiraswasta',
                    'no_hp' => '081298765432',
                    'alamat' => 'Jl. Merdeka No. 10',
                ],
            ],
            'rombel_id' => $this->rombel->id,
            'nomor_absen' => 1,
        ];

        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('siswa.store'), $payload);

        $response->assertRedirect();

        $this->assertDatabaseHas('siswa', [
            'sekolah_id' => $this->sekolah->id,
            'nisn' => '0012345678',
            'nama' => 'Budi Pratama',
        ]);

        $siswa = Siswa::where('nisn', '0012345678')->firstOrFail();

        $this->assertDatabaseHas('wali_siswa', [
            'sekolah_id' => $this->sekolah->id,
            'siswa_id' => $siswa->id,
            'nama' => 'Herman Pratama',
        ]);

        $this->assertDatabaseHas('anggota_rombel', [
            'sekolah_id' => $this->sekolah->id,
            'rombel_id' => $this->rombel->id,
            'siswa_id' => $siswa->id,
            'semester_id' => $this->semesterAktif->id,
            'nomor_absen' => 1,
        ]);
    }

    public function test_nisn_unique_validation_rejects_duplicate_across_schools(): void
    {
        $sekolahB = Sekolah::factory()->create();
        Siswa::factory()->create([
            'sekolah_id' => $sekolahB->id,
            'nisn' => '0099887766',
        ]);

        // Operator di sekolah A mencoba mendaftarkan NISN yang sama
        $payload = [
            'nisn' => '0099887766',
            'nik' => '3201011205080002',
            'nama' => 'Siswa Baru',
            'jenis_kelamin' => 'P',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '2008-01-15',
            'agama' => 'Islam',
            'status' => 'aktif',
        ];

        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('siswa.store'), $payload);

        $response->assertSessionHasErrors(['nisn' => 'NISN sudah terdaftar di sistem.']);
    }

    public function test_nisn_of_soft_deleted_student_can_be_reused(): void
    {
        $oldStudent = Siswa::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nisn' => '0055443322',
        ]);

        // Soft delete siswa lama
        $oldStudent->delete();
        $this->assertSoftDeleted('siswa', ['id' => $oldStudent->id]);

        // Mendaftarkan siswa baru dengan NISN yang sama harus berhasil (Opsi A)
        $payload = [
            'nisn' => '0055443322',
            'nik' => '3201011205080003',
            'nama' => 'Siswa Pengganti Koreksi',
            'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Surabaya',
            'tanggal_lahir' => '2008-03-20',
            'agama' => 'Islam',
            'status' => 'aktif',
        ];

        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('siswa.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('siswa', [
            'nisn' => '0055443322',
            'nama' => 'Siswa Pengganti Koreksi',
            'deleted_at' => null,
        ]);
    }

    public function test_operator_can_update_student_and_wali(): void
    {
        $siswa = Siswa::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nama' => 'Nama Lama',
        ]);

        $wali = WaliSiswa::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'siswa_id' => $siswa->id,
            'nama' => 'Wali Lama',
        ]);

        $payload = [
            'nisn' => $siswa->nisn,
            'nik' => $siswa->nik,
            'nama' => 'Nama Baru Diperbarui',
            'jenis_kelamin' => $siswa->jenis_kelamin->value,
            'tempat_lahir' => $siswa->tempat_lahir,
            'tanggal_lahir' => $siswa->tanggal_lahir->format('Y-m-d'),
            'agama' => $siswa->agama,
            'alamat' => 'Alamat Baru',
            'no_hp' => '08111222333',
            'status' => 'aktif',
            'wali' => [
                [
                    'id' => $wali->id,
                    'hubungan' => 'ayah',
                    'nama' => 'Wali Baru Diperbarui',
                    'pekerjaan' => 'PNS',
                    'no_hp' => '08555666777',
                    'alamat' => 'Alamat Baru',
                ],
            ],
        ];

        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->put(route('siswa.update', $siswa->id), $payload);

        $response->assertRedirect();

        $this->assertDatabaseHas('siswa', [
            'id' => $siswa->id,
            'nama' => 'Nama Baru Diperbarui',
            'alamat' => 'Alamat Baru',
        ]);

        $this->assertDatabaseHas('wali_siswa', [
            'id' => $wali->id,
            'nama' => 'Wali Baru Diperbarui',
            'pekerjaan' => 'PNS',
        ]);
    }

    public function test_operator_can_soft_delete_student(): void
    {
        $siswa = Siswa::factory()->create(['sekolah_id' => $this->sekolah->id]);

        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->delete(route('siswa.destroy', $siswa->id));

        $response->assertRedirect();
        $this->assertSoftDeleted('siswa', ['id' => $siswa->id]);
    }

    public function test_tenant_isolation_404_when_accessing_other_school_student(): void
    {
        $sekolahB = Sekolah::factory()->create();
        $siswaB = Siswa::factory()->create(['sekolah_id' => $sekolahB->id]);

        // Operator di sekolah A mencoba mengupdate atau menghapus siswa di sekolah B
        $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->delete(route('siswa.destroy', $siswaB->id))
            ->assertNotFound();

        $this->assertDatabaseHas('siswa', ['id' => $siswaB->id, 'deleted_at' => null]);
    }

    public function test_sql_scopes_for_data_kelengkapan(): void
    {
        // 1. Siswa Lengkap
        $siswaLengkap = Siswa::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nisn' => '0011223344',
            'nik' => '3201011205080005',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '2008-01-01',
            'alamat' => 'Jl. Lengkap No. 1',
            'no_hp' => '08123456789',
        ]);
        WaliSiswa::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'siswa_id' => $siswaLengkap->id,
            'nama' => 'Ayah Lengkap',
            'pekerjaan' => 'Dokter',
            'no_hp' => '08123456789',
        ]);

        // 2. Siswa Belum Lengkap (tidak ada alamat & no_hp, tidak ada wali)
        $siswaBelumLengkap = Siswa::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nisn' => '0099881122',
            'alamat' => null,
            'no_hp' => null,
        ]);

        $lengkapIds = Siswa::dataLengkap()->pluck('id')->all();
        $belumLengkapIds = Siswa::dataBelumLengkap()->pluck('id')->all();

        $this->assertContains($siswaLengkap->id, $lengkapIds);
        $this->assertNotContains($siswaBelumLengkap->id, $lengkapIds);

        $this->assertContains($siswaBelumLengkap->id, $belumLengkapIds);
        $this->assertNotContains($siswaLengkap->id, $belumLengkapIds);
    }

    public function test_nisn_duplicate_in_same_school_is_rejected(): void
    {
        Siswa::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nisn' => '0012345678',
        ]);

        $payload = [
            'nisn' => '0012345678',
            'nik' => '3201011205080099',
            'nama' => 'Siswa Kembar NISN',
            'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '2008-05-12',
            'agama' => 'Islam',
            'status' => 'aktif',
        ];

        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('siswa.store'), $payload);

        $response->assertSessionHasErrors(['nisn']);
    }

    public function test_nik_invalid_validation_rejects_malformed_nik(): void
    {
        // 1. NIK kurang dari 16 digit
        $responseShort = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('siswa.store'), [
                'nisn' => '0012345679',
                'nik' => '320101120508',
                'nama' => 'Siswa NIK Pendek',
                'jenis_kelamin' => 'L',
                'tempat_lahir' => 'Bandung',
                'tanggal_lahir' => '2008-05-12',
                'agama' => 'Islam',
                'status' => 'aktif',
            ]);
        $responseShort->assertSessionHasErrors(['nik']);

        // 2. NIK mengandung huruf
        $responseAlpha = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('siswa.store'), [
                'nisn' => '0012345679',
                'nik' => '320101120508000A',
                'nama' => 'Siswa NIK Huruf',
                'jenis_kelamin' => 'L',
                'tempat_lahir' => 'Bandung',
                'tanggal_lahir' => '2008-05-12',
                'agama' => 'Islam',
                'status' => 'aktif',
            ]);
        $responseAlpha->assertSessionHasErrors(['nik']);
    }

    public function test_nik_and_birthdate_mismatch_is_non_blocking_warning(): void
    {
        // Tanggal lahir di NIK adalah 15-05-08 (15 Mei 2008), tapi input tanggal_lahir adalah 2008-01-20
        // Backend tetap memvalidasi format dan menyimpan (warning hanya di UI, tidak blocking simpan)
        $payload = [
            'nisn' => '0088776655',
            'nik' => '3201011505080001',
            'nama' => 'Siswa Mismatch Warning',
            'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '2008-01-20',
            'agama' => 'Islam',
            'status' => 'aktif',
        ];

        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('siswa.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('siswa', [
            'sekolah_id' => $this->sekolah->id,
            'nisn' => '0088776655',
            'nik' => '3201011505080001',
            'tanggal_lahir' => '2008-01-20',
        ]);
    }

    public function test_tenant_isolation_returns_404_for_show_edit_update_and_delete(): void
    {
        $sekolahB = Sekolah::factory()->create();
        $siswaB = Siswa::factory()->create(['sekolah_id' => $sekolahB->id]);

        // Show: 404
        $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('siswa.show', $siswaB->id))
            ->assertNotFound();

        // Edit: 404
        $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('siswa.edit', $siswaB->id))
            ->assertNotFound();

        // Update: 404
        $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->put(route('siswa.update', $siswaB->id), ['alamat' => 'Alamat Usil'])
            ->assertNotFound();

        // Delete: 404
        $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->delete(route('siswa.destroy', $siswaB->id))
            ->assertNotFound();
    }

    public function test_restoring_student_with_conflicting_active_nisn_throws_exception(): void
    {
        $studentA = Siswa::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nisn' => '0077889900',
        ]);

        // Soft delete student A
        $studentA->delete();
        $this->assertSoftDeleted('siswa', ['id' => $studentA->id]);

        // Buat student B dengan NISN yang sama (aktif)
        Siswa::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nisn' => '0077889900',
        ]);

        // Mencoba memulihkan student A harus memicu exception dari event restoring
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('sudah aktif digunakan oleh siswa lain');

        $studentA->restore();
    }

    public function test_siswa_wali_and_anggota_rombel_record_activity_logs(): void
    {
        Activity::query()->delete();

        $payload = [
            'nisn' => '0033445566',
            'nik' => '3201011205080007',
            'nama' => 'Siswa Audit Test',
            'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '2008-05-12',
            'agama' => 'Islam',
            'status' => 'aktif',
            'wali' => [
                [
                    'hubungan' => 'ayah',
                    'nama' => 'Ayah Audit',
                ],
            ],
            'rombel_id' => $this->rombel->id,
        ];

        $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('siswa.store'), $payload);

        $siswa = Siswa::where('nisn', '0033445566')->firstOrFail();

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Siswa::class,
            'subject_id' => $siswa->id,
            'event' => 'created',
        ]);

        $wali = WaliSiswa::where('siswa_id', $siswa->id)->firstOrFail();
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => WaliSiswa::class,
            'subject_id' => $wali->id,
            'event' => 'created',
        ]);

        $anggota = AnggotaRombel::where('siswa_id', $siswa->id)->firstOrFail();
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => AnggotaRombel::class,
            'subject_id' => $anggota->id,
            'event' => 'created',
        ]);
    }
}
