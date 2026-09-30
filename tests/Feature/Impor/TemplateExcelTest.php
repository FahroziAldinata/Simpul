<?php

namespace Tests\Feature\Impor;

use App\Models\Sekolah;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class TemplateExcelTest extends TestCase
{
    use RefreshDatabase;

    protected Sekolah $sekolah;

    protected User $operator;

    protected User $guru;

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

        $this->guru = User::factory()->create([
            'sekolah_id' => $this->sekolah->id,
        ]);
        $this->guru->assignRole('guru');
    }

    public function test_guest_cannot_download_template(): void
    {
        $response = $this->get(route('siswa.impor.template'));
        $response->assertRedirect('/login');
    }

    public function test_unauthorized_user_cannot_download_template(): void
    {
        $response = $this->actingAs($this->guru)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('siswa.impor.template'));

        $response->assertForbidden();
    }

    public function test_operator_can_download_template_with_correct_sheets_headers_and_validation(): void
    {
        $response = $this->actingAs($this->operator)
            ->withSession(['sekolah_id' => $this->sekolah->id])
            ->get(route('siswa.impor.template'));

        $response->assertOk();
        $response->assertHeader('content-disposition');

        // Save downloaded content to temp file to inspect with PhpSpreadsheet
        $tempFile = tempnam(sys_get_temp_dir(), 'tpl_').'.xlsx';
        file_put_contents($tempFile, $response->streamedContent());

        $spreadsheet = IOFactory::load($tempFile);

        // Check sheets
        $sheetNames = $spreadsheet->getSheetNames();
        expect($sheetNames)->toContain('Data Siswa');
        expect($sheetNames)->toContain('Petunjuk Pengisian');

        // Check Data Siswa headers
        $dataSheet = $spreadsheet->getSheetByName('Data Siswa');
        expect($dataSheet)->not->toBeNull();

        $expectedHeaders = [
            'NISN',
            'NIK',
            'Nama Lengkap',
            'Jenis Kelamin',
            'Tempat Lahir',
            'Tanggal Lahir',
            'Agama',
            'Alamat',
            'No. HP',
            'Rombel',
        ];

        for ($i = 0; $i < count($expectedHeaders); $i++) {
            $colLetter = Coordinate::stringFromColumnIndex($i + 1);
            $cellValue = $dataSheet->getCell("{$colLetter}1")->getValue();
            expect($cellValue)->toBe($expectedHeaders[$i]);
        }

        // Check dropdown validation on row 2
        $jkValidation = $dataSheet->getCell('D2')->getDataValidation();
        expect($jkValidation->getType())->toBe(DataValidation::TYPE_LIST);
        expect($jkValidation->getFormula1())->toBe('"L,P"');

        $agamaValidation = $dataSheet->getCell('G2')->getDataValidation();
        expect($agamaValidation->getType())->toBe(DataValidation::TYPE_LIST);
        expect($agamaValidation->getFormula1())->toBe('"Islam,Kristen,Katolik,Hindu,Buddha,Konghucu"');

        // Check Petunjuk Pengisian headers
        $petunjukSheet = $spreadsheet->getSheetByName('Petunjuk Pengisian');
        expect($petunjukSheet)->not->toBeNull();
        expect($petunjukSheet->getCell('A1')->getValue())->toBe('Nama Kolom');
        expect($petunjukSheet->getCell('B1')->getValue())->toBe('Wajib?');

        @unlink($tempFile);
    }
}
