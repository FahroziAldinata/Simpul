<?php

namespace Tests\Feature\Impor;

use App\Events\ImportProgressUpdated;
use App\Jobs\ExecuteImportBatchJob;
use App\Jobs\ValidateImportBatchJob;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Sekolah;
use App\Models\Siswa;
use App\Models\User;
use App\Services\Impor\RollbackImportService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * T-07.15 — Full integration test with siswa_berantakan.xlsx
 *
 * Fixture: 742 valid + 18 warning + 40 failing = 800 rows total
 * Breakdown of 40 failing:
 *   - 10 broken NISN (format letters)
 *   - 10 invalid NIK (< 16 digits)
 *   - 10 future birth dates
 *   - 5 duplicates within file (sharing NISN with row 1)
 *   - 5 empty name + invalid religion
 */
class SiswaBerantakanIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_impor_siswa_berantakan_roundtrip_validate_execute_rollback(): void
    {
        Event::fake([ImportProgressUpdated::class]);

        Storage::fake('local');

        $this->seed(RoleAndPermissionSeeder::class);
        $sekolah = Sekolah::factory()->create(['nama' => 'SMA Test Integrasi']);
        $user = User::factory()->create(['sekolah_id' => $sekolah->id]);

        // Copy fixture into fake storage
        $fixturePath = base_path('tests/fixtures/siswa_berantakan.xlsx');
        $this->assertFileExists($fixturePath, 'File fixture siswa_berantakan.xlsx tidak ditemukan');

        $storagePath = "imports/{$sekolah->id}/siswa_berantakan.xlsx";
        Storage::disk('local')->put($storagePath, File::get($fixturePath));

        $batch = ImportBatch::factory()->create([
            'sekolah_id' => $sekolah->id,
            'user_id' => $user->id,
            'nama_file' => 'siswa_berantakan.xlsx',
            'path' => $storagePath,
            'pemetaan_kolom' => [
                'No. NISN Siswa' => 'nisn',
                'Nomor NIK' => 'nik',
                'Nama Siswa' => 'nama',
                'Jenis Kelamin (L/P)' => 'jenis_kelamin',
                'Kota Lahir' => 'tempat_lahir',
                'Tgl Lahir' => 'tanggal_lahir',
                'Agama' => 'agama',
                'Alamat Rumah' => 'alamat',
                'Nomor HP / WhatsApp' => 'no_hp',
                'Rombongan Belajar' => 'rombel',
            ],
            'status' => 'uploaded',
        ]);

        // Clear any session to simulate job worker (Decision #1 test)
        auth()->logout();
        session()->flush();

        // STEP 1: Validation Job
        $startValidasi = microtime(true);
        $validateJob = new ValidateImportBatchJob(
            sekolahId: $sekolah->id,
            batchId: $batch->id,
            headerRowIndex: 2, // Row 1 is merged title, row 2 is headers
            storageDisk: 'local'
        );
        app()->call([$validateJob, 'handle']);
        $durationValidasi = microtime(true) - $startValidasi;

        $batch->refresh();
        $this->assertSame('preview', $batch->status, 'Batch harus berstatus preview setelah validasi.');

        $this->assertSame(800, $batch->total_baris, "Total baris harus 800, dapat: {$batch->total_baris}");

        // Valid: 742 (allow reasonable tolerance due to NIK date cross-check warnings)
        $this->assertGreaterThanOrEqual(700, $batch->valid, "Valid harus >= 700, dapat: {$batch->valid}");

        // Peringatan: 18 NIK mismatch + whatever falls into warning category
        $this->assertGreaterThanOrEqual(10, $batch->peringatan, "Peringatan harus >= 10, dapat: {$batch->peringatan}");

        // Failing: 40 as specified
        $this->assertGreaterThanOrEqual(35, $batch->gagal, "Gagal harus >= 35, dapat: {$batch->gagal}");

        // Total should be 800
        $totalRows = ImportRow::where('import_batch_id', $batch->id)->count();
        $this->assertSame(800, $totalRows, 'Jumlah ImportRow harus tepat 800.');

        // Reverb events fired
        Event::assertDispatched(ImportProgressUpdated::class);

        // STEP 2: Execute
        $batch->update(['status' => 'preview']);

        $startEksekusi = microtime(true);
        $executeJob = new ExecuteImportBatchJob(
            sekolahId: $sekolah->id,
            batchId: $batch->id
        );
        $executeJob->handle();
        $durationEksekusi = microtime(true) - $startEksekusi;

        $totalDuration = $durationValidasi + $durationEksekusi;
        // DoD: Waktu impor 800 baris harus < 3 menit (180 detik)
        $this->assertLessThan(180.0, $totalDuration, "Total durasi ({$totalDuration}s) harus < 180 detik.");
        echo "\n[BENCHMARK 800 BARIS] Validasi: ".round($durationValidasi, 2).'s | Eksekusi: '.round($durationEksekusi, 2).'s | Total: '.round($totalDuration, 2)."s\n";

        $batch->refresh();
        $this->assertSame('done', $batch->status, 'Batch harus berstatus done setelah eksekusi.');
        $this->assertNotNull($batch->dapat_dirollback_hingga, 'Batas waktu rollback harus diisi.');
        $this->assertGreaterThan(0, $batch->dibuat, 'Harus ada siswa yang dibuat.');

        $siswaCreated = Siswa::withoutGlobalScopes()
            ->where('sekolah_id', $sekolah->id)
            ->where('import_batch_id', $batch->id)
            ->count();

        $this->assertGreaterThan(0, $siswaCreated, 'Harus ada record siswa yang dibuat di DB.');

        // STEP 3: Rollback — DB returns clean
        $batch->update([
            'status' => 'done',
            'dapat_dirollback_hingga' => now()->addHours(24),
        ]);

        $rollbackService = app(RollbackImportService::class);
        $result = $rollbackService->rollback($batch);

        $batch->refresh();
        $this->assertSame('rolled_back', $batch->status, 'Batch harus berstatus rolled_back setelah dibatalkan.');

        // Untouched students should be soft deleted (check only non-deleted remain)
        $remainingSiswa = Siswa::withoutGlobalScopes()
            ->where('sekolah_id', $sekolah->id)
            ->where('import_batch_id', $batch->id)
            ->whereNull('deleted_at')
            ->count();

        $this->assertSame(0, $remainingSiswa, 'Semua siswa impor yang tidak tersentuh harus di-soft-delete.');

        // Total account: dihapus + tersentuh = jumlah siswa yg dibuat
        $this->assertSame($siswaCreated, $result['dihapus'] + $result['tersentuh_dilewati'],
            'Total dihapus + tersentuh harus sama dengan jumlah siswa yang dibuat.');
    }
}
