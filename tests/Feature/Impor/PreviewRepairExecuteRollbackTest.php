<?php

namespace Tests\Feature\Impor;

use App\Jobs\ExecuteImportBatchJob;
use App\Models\AnggotaRombel;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Rombel;
use App\Models\Sekolah;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class PreviewRepairExecuteRollbackTest extends TestCase
{
    use RefreshDatabase;

    protected Sekolah $sekolah;

    protected User $operator;

    protected Semester $semester;

    protected Rombel $rombel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->sekolah = Sekolah::factory()->create();
        setPermissionsTeamId($this->sekolah->id);

        $this->operator = User::factory()->create([
            'sekolah_id' => $this->sekolah->id,
        ]);
        $this->operator->assignRole('operator');

        $ta = TahunAjaran::factory()->create(['sekolah_id' => $this->sekolah->id]);
        $this->semester = Semester::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'tahun_ajaran_id' => $ta->id,
            'is_aktif' => true,
        ]);
        $this->rombel = Rombel::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'semester_id' => $this->semester->id,
        ]);
    }

    public function test_inline_row_repair_updates_status_and_recalculates_batch_statistics(): void
    {
        $batch = ImportBatch::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'user_id' => $this->operator->id,
            'status' => 'preview',
            'total_baris' => 1,
            'valid' => 0,
            'peringatan' => 0,
            'gagal' => 1,
        ]);

        $row = ImportRow::factory()->create([
            'import_batch_id' => $batch->id,
            'nomor_baris' => 2,
            'status' => 'gagal',
            'data_mentah' => ['NISN' => '123'],
            'data_bersih' => ['nisn' => '123'],
            'errors' => ['nisn' => 'NISN harus berupa 10 digit angka.'],
        ]);

        // Operator repairs the NISN inline
        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->putJson(route('siswa.impor.rows.update', [$batch->id, $row->id]), [
                'data' => [
                    'nisn' => '0081234567',
                    'nik' => '3201011705080001',
                    'nama' => 'Budi Santoso',
                    'jenis_kelamin' => 'L',
                    'tempat_lahir' => 'Jakarta',
                    'tanggal_lahir' => '2008-05-17',
                    'agama' => 'Islam',
                ],
            ]);

        $response->assertOk();
        $row->refresh();
        $batch->refresh();

        expect($row->status)->toBe('valid');
        expect($row->errors)->toBeNull();
        expect($batch->valid)->toBe(1);
        expect($batch->gagal)->toBe(0);
    }

    public function test_resolve_duplicate_action(): void
    {
        $batch = ImportBatch::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'user_id' => $this->operator->id,
            'status' => 'preview',
        ]);

        $row = ImportRow::factory()->create([
            'import_batch_id' => $batch->id,
            'nomor_baris' => 2,
            'status' => 'peringatan',
            'aksi_duplikat' => 'lewati',
        ]);

        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->postJson(route('siswa.impor.rows.resolusi', [$batch->id, $row->id]), [
                'aksi' => 'perbarui',
            ]);

        $response->assertOk();
        $row->refresh();
        expect($row->aksi_duplikat)->toBe('perbarui');
    }

    public function test_execute_import_inserts_students_and_updates_status(): void
    {
        $batch = ImportBatch::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'user_id' => $this->operator->id,
            'status' => 'preview',
            'total_baris' => 2,
            'valid' => 2,
        ]);

        $row1 = ImportRow::factory()->create([
            'import_batch_id' => $batch->id,
            'nomor_baris' => 2,
            'status' => 'valid',
            'data_bersih' => [
                'nisn' => '0081111111',
                'nik' => '3201011705080001',
                'nama' => 'Siswa Satu',
                'jenis_kelamin' => 'L',
                'tempat_lahir' => 'Bandung',
                'tanggal_lahir' => '2008-05-17',
                'agama' => 'Islam',
            ],
        ]);

        $row2 = ImportRow::factory()->create([
            'import_batch_id' => $batch->id,
            'nomor_baris' => 3,
            'status' => 'valid',
            'data_bersih' => [
                'nisn' => '0082222222',
                'nik' => '3201011705080002',
                'nama' => 'Siswa Dua',
                'jenis_kelamin' => 'P',
                'tempat_lahir' => 'Cimahi',
                'tanggal_lahir' => '2008-05-17',
                'agama' => 'Islam',
            ],
        ]);

        // Run execute job
        $job = new ExecuteImportBatchJob($this->sekolah->id, $batch->id);
        $job->handle();

        $batch->refresh();
        expect($batch->status)->toBe('done');
        expect($batch->dibuat)->toBe(2);
        expect($batch->dapat_dirollback_hingga)->not->toBeNull();

        $row1->refresh();
        expect($row1->status)->toBe('diimpor');
        expect($row1->model_id)->not->toBeNull();

        $this->assertDatabaseHas('siswa', [
            'sekolah_id' => $this->sekolah->id,
            'nisn' => '0081111111',
            'import_batch_id' => $batch->id,
        ]);
    }

    public function test_execute_import_updates_existing_student_and_records_activity_log(): void
    {
        $existingSiswa = Siswa::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nisn' => '0089999999',
            'nama' => 'Nama Lama',
            'alamat' => 'Alamat Lama',
        ]);

        $batch = ImportBatch::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'user_id' => $this->operator->id,
            'status' => 'preview',
            'total_baris' => 1,
            'valid' => 1,
        ]);

        $row = ImportRow::factory()->create([
            'import_batch_id' => $batch->id,
            'nomor_baris' => 2,
            'status' => 'valid',
            'aksi_duplikat' => 'perbarui',
            'data_bersih' => [
                'nisn' => '0089999999',
                'nik' => $existingSiswa->nik,
                'nama' => 'Nama Baru Diperbarui',
                'jenis_kelamin' => 'L',
                'tempat_lahir' => 'Bandung',
                'tanggal_lahir' => '2008-05-17',
                'agama' => 'Islam',
                'alamat' => 'Alamat Baru Jalan Merdeka',
            ],
        ]);

        $job = new ExecuteImportBatchJob($this->sekolah->id, $batch->id);
        $job->handle();

        $batch->refresh();
        expect($batch->status)->toBe('done');
        expect($batch->diperbarui)->toBe(1);

        $existingSiswa->refresh();
        expect($existingSiswa->nama)->toBe('Nama Baru Diperbarui');
        expect($existingSiswa->alamat)->toBe('Alamat Baru Jalan Merdeka');

        // Verify activity log recorded the update
        $activity = Activity::where('subject_type', Siswa::class)
            ->where('subject_id', $existingSiswa->id)
            ->where('event', 'updated')
            ->latest('id')
            ->first();

        expect($activity)->not->toBeNull();
        expect($activity->properties['attributes']['nama'])->toBe('Nama Baru Diperbarui');
        expect($activity->properties['old']['nama'])->toBe('Nama Lama');
    }

    public function test_rollback_batch_deletes_untouched_and_guards_touched_students(): void
    {
        $batch = ImportBatch::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'user_id' => $this->operator->id,
            'status' => 'done',
            'dapat_dirollback_hingga' => now()->addHours(24),
        ]);

        // Student 1: Untouched -> will be soft deleted
        $siswaUntouched = Siswa::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nisn' => '0081111111',
            'import_batch_id' => $batch->id,
        ]);

        // Student 2: Touched (assigned to rombel) -> will be preserved
        $siswaTouched = Siswa::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'nisn' => '0082222222',
            'nama' => 'Siswa Tersentuh Rombel',
            'import_batch_id' => $batch->id,
        ]);
        AnggotaRombel::create([
            'sekolah_id' => $this->sekolah->id,
            'siswa_id' => $siswaTouched->id,
            'rombel_id' => $this->rombel->id,
            'semester_id' => $this->semester->id,
        ]);

        // Execute rollback via HTTP (JSON) to assert explicit return payload
        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->postJson(route('siswa.impor.rollback', $batch->id));

        $response->assertOk();
        $data = $response->json();

        expect($data['result']['dihapus'])->toBe(1);
        expect($data['result']['tersentuh_dilewati'])->toBe(1);
        expect($data['result']['detail_tersentuh'])->toHaveCount(1);
        expect($data['result']['detail_tersentuh'][0]['nisn'])->toBe('0082222222');
        expect($data['result']['detail_tersentuh'][0]['alasan'])->toContain('rombongan belajar');

        $batch->refresh();
        expect($batch->status)->toBe('rolled_back');

        // Untouched student is soft deleted
        expect(Siswa::find($siswaUntouched->id))->toBeNull();
        expect(Siswa::withTrashed()->find($siswaUntouched->id)?->deleted_at)->not->toBeNull();

        // Touched student is preserved!
        expect(Siswa::find($siswaTouched->id))->not->toBeNull();
    }

    public function test_export_failed_rows_downloads_spreadsheet(): void
    {
        $batch = ImportBatch::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'user_id' => $this->operator->id,
            'status' => 'preview',
        ]);

        ImportRow::factory()->create([
            'import_batch_id' => $batch->id,
            'nomor_baris' => 2,
            'status' => 'gagal',
            'data_mentah' => ['NISN' => '123', 'Nama' => 'Siswa Gagal'],
            'errors' => ['nisn' => 'NISN harus berupa 10 digit angka.'],
        ]);

        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('siswa.impor.export-failed', $batch->id));

        $response->assertOk();
        $response->assertHeader('content-disposition');
    }
}
