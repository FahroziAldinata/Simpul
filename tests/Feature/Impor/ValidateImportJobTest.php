<?php

namespace Tests\Feature\Impor;

use App\Events\ImportProgressUpdated;
use App\Jobs\ValidateImportBatchJob;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Sekolah;
use App\Models\Siswa;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ValidateImportJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_validate_import_job_runs_successfully_without_active_http_session(): void
    {
        Event::fake([ImportProgressUpdated::class]);
        Storage::fake('local');

        $this->seed(RoleAndPermissionSeeder::class);

        $sekolahA = Sekolah::factory()->create(['nama' => 'Sekolah A']);
        $sekolahB = Sekolah::factory()->create(['nama' => 'Sekolah B']);

        $operator = User::factory()->create([
            'sekolah_id' => $sekolahA->id,
        ]);

        // Pre-existing student in Sekolah A (same school duplicate)
        $siswaSameSchool = Siswa::factory()->create([
            'sekolah_id' => $sekolahA->id,
            'nisn' => '0081111111',
            'nama' => 'Budi Lama',
        ]);

        // Pre-existing student in Sekolah B (cross-tenant duplicate)
        $siswaOtherSchool = Siswa::factory()->create([
            'sekolah_id' => $sekolahB->id,
            'nisn' => '0082222222',
            'nama' => 'Siswa Sekolah Lain',
        ]);

        // Create spreadsheet with:
        // Row 1: Headers
        // Row 2: Valid student
        // Row 3: Warning student (NIK date mismatch)
        // Row 4: Duplicate in same school (0081111111) -> peringatan
        // Row 5: Duplicate in other school (0082222222) -> gagal (neutral message)
        // Row 6: Invalid NISN format (123) -> gagal
        // Row 7 & 8: Duplicate in-file (0089999999) -> gagal
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['NISN', 'NIK', 'Nama Lengkap', 'Jenis Kelamin', 'Tempat Lahir', 'Tanggal Lahir', 'Agama'];
        foreach ($headers as $idx => $header) {
            $colLetter = Coordinate::stringFromColumnIndex($idx + 1);
            $sheet->setCellValue("{$colLetter}1", $header);
        }

        $dataRows = [
            // Row 2: Valid
            ['0083333333', '3201011705080001', 'Ahmad Dahlan', 'L', 'Yogyakarta', '2008-05-17', 'Islam'],
            // Row 3: Warning (NIK 170508 mismatch with 2008-08-20)
            ['0084444444', '3201011705080002', 'Siti Fatimah', 'P', 'Bandung', '2008-08-20', 'Islam'],
            // Row 4: Duplicate in same school
            ['0081111111', '3201011705080003', 'Budi Baru', 'L', 'Jakarta', '2008-05-17', 'Islam'],
            // Row 5: Duplicate in other school
            ['0082222222', '3201011705080004', 'Deni Raharja', 'L', 'Surabaya', '2008-05-17', 'Islam'],
            // Row 6: Invalid NISN format
            ['123', '3201011705080005', 'Candra Wijaya', 'L', 'Semarang', '2008-05-17', 'Islam'],
            // Row 7: Duplicate in file #1
            ['0089999999', '3201011705080006', 'Kembar Satu', 'L', 'Medan', '2008-05-17', 'Islam'],
            // Row 8: Duplicate in file #2
            ['0089999999', '3201011705080007', 'Kembar Dua', 'L', 'Medan', '2008-05-17', 'Islam'],
        ];

        foreach ($dataRows as $rIdx => $rData) {
            $rowNum = $rIdx + 2;
            foreach ($rData as $cIdx => $val) {
                $colLetter = Coordinate::stringFromColumnIndex($cIdx + 1);
                $sheet->setCellValueExplicit("{$colLetter}{$rowNum}", $val, DataType::TYPE_STRING);
            }
        }

        $tempPath = 'imports/test_siswa.xlsx';
        $fullTempPath = Storage::disk('local')->path($tempPath);
        @mkdir(dirname($fullTempPath), 0755, true);
        $writer = new Xlsx($spreadsheet);
        $writer->save($fullTempPath);

        $batch = ImportBatch::factory()->create([
            'sekolah_id' => $sekolahA->id,
            'user_id' => $operator->id,
            'tipe' => 'siswa',
            'nama_file' => 'test_siswa.xlsx',
            'path' => $tempPath,
            'pemetaan_kolom' => [
                'NISN' => 'nisn',
                'NIK' => 'nik',
                'Nama Lengkap' => 'nama',
                'Jenis Kelamin' => 'jenis_kelamin',
                'Tempat Lahir' => 'tempat_lahir',
                'Tanggal Lahir' => 'tanggal_lahir',
                'Agama' => 'agama',
            ],
            'status' => 'uploaded',
        ]);

        // CRITICAL CHECK FOR DECISION #1:
        // Clear any auth and session to simulate worker CLI environment
        auth()->logout();
        session()->flush();

        // Instantiate and run job with explicit sekolahId
        $job = new ValidateImportBatchJob(
            sekolahId: $sekolahA->id,
            batchId: $batch->id,
            headerRowIndex: 1,
            storageDisk: 'local'
        );

        app()->call([$job, 'handle']);

        // Verify batch results
        $batch->refresh();
        expect($batch->status)->toBe('preview');
        expect($batch->total_baris)->toBe(7);
        expect($batch->valid)->toBe(1);
        expect($batch->peringatan)->toBe(2); // Row 3 (NIK warning) + Row 4 (Same school duplicate warning)
        expect($batch->gagal)->toBe(4); // Row 5 (Other school duplicate) + Row 6 (Invalid format) + Rows 7,8 (In-file duplicate)

        // Verify import_rows
        $rows = ImportRow::where('import_batch_id', $batch->id)->orderBy('nomor_baris')->get();
        expect($rows)->toHaveCount(7);

        // Verify other school error is neutral without leaking tenant
        $rowOtherSchool = $rows->firstWhere('nomor_baris', 5);
        expect($rowOtherSchool->status)->toBe('gagal');
        expect($rowOtherSchool->errors['nisn'])->toBe('NISN sudah terdaftar di sistem. Hubungi Super Admin untuk verifikasi lebih lanjut.');

        // Verify Reverb broadcast event was dispatched
        Event::assertDispatched(ImportProgressUpdated::class);
    }
}
