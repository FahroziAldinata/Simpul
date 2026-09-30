<?php

namespace Tests\Feature\Impor;

use App\Models\ImportBatch;
use App\Models\Sekolah;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class UploadAndMappingTest extends TestCase
{
    use RefreshDatabase;

    protected Sekolah $sekolah;

    protected User $operator;

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
    }

    public function test_upload_excel_validates_file_extension_and_creates_batch(): void
    {
        Storage::fake('local');

        // 1. Invalid extension (.txt)
        $invalidFile = UploadedFile::fake()->create('test.txt', 100, 'text/plain');
        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('siswa.impor.upload'), [
                'file' => $invalidFile,
            ]);
        $response->assertSessionHasErrors(['file']);

        // 2. Valid .xlsx file
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'NISN');
        $sheet->setCellValue('B1', 'Nama Lengkap');
        $sheet->setCellValue('A2', '0081234567');
        $sheet->setCellValue('B2', 'Budi Utomo');

        $tempFile = tempnam(sys_get_temp_dir(), 'test_up_').'.xlsx';
        (new Xlsx($spreadsheet))->save($tempFile);

        $uploadedFile = new UploadedFile($tempFile, 'data_siswa.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('siswa.impor.upload'), [
                'file' => $uploadedFile,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('import_batches', [
            'sekolah_id' => $this->sekolah->id,
            'nama_file' => 'data_siswa.xlsx',
            'status' => 'uploaded',
            'total_baris' => 1,
        ]);

        @unlink($tempFile);
    }

    public function test_save_mapping_validates_mandatory_columns(): void
    {
        Queue::fake();

        $batch = ImportBatch::factory()->create([
            'sekolah_id' => $this->sekolah->id,
            'user_id' => $this->operator->id,
        ]);

        // Incomplete mapping (missing NIK, Agama, etc.)
        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('siswa.impor.mapping.save', $batch->id), [
                'mapping' => [
                    'NISN' => 'nisn',
                    'Nama' => 'nama',
                ],
            ]);

        $response->assertSessionHasErrors(['mapping']);

        // Complete mapping
        $completeMapping = [
            'NISN' => 'nisn',
            'NIK' => 'nik',
            'Nama Siswa' => 'nama',
            'JK' => 'jenis_kelamin',
            'Tempat Lahir' => 'tempat_lahir',
            'Tgl Lahir' => 'tanggal_lahir',
            'Agama' => 'agama',
        ];

        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->post(route('siswa.impor.mapping.save', $batch->id), [
                'mapping' => $completeMapping,
                'template_name' => 'Template Dapodik 2026',
            ]);

        $response->assertRedirect(route('siswa.impor.preview', $batch->id));

        $batch->refresh();
        expect($batch->pemetaan_kolom)->toEqualCanonicalizing($completeMapping);
        expect($batch->status)->toBe('validating');

        // Verify template saved in user preference
        $this->operator->refresh();
        expect($this->operator->preferences['import_mapping_templates']['Template Dapodik 2026'])->toEqualCanonicalizing($completeMapping);
    }
}
