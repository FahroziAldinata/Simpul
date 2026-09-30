<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class TemplateSiswaDataSheet implements ShouldAutoSize, WithColumnFormatting, WithEvents, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'Data Siswa';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
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
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT, // NISN
            'B' => NumberFormat::FORMAT_TEXT, // NIK
            'I' => NumberFormat::FORMAT_TEXT, // No. HP
        ];
    }

    /**
     * @return array<class-string, \Closure(AfterSheet): void>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                // Freeze header row
                $sheet->freezePane('A2');

                // Style header row
                $headerRange = 'A1:J1';
                $sheet->getStyle($headerRange)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                        'size' => 11,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '1E293B'], // Slate-800
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '0F172A'],
                        ],
                    ],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(28);

                // Data validation for Jenis Kelamin (Column D)
                $jkValidation = new DataValidation;
                $jkValidation->setType(DataValidation::TYPE_LIST);
                $jkValidation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                $jkValidation->setAllowBlank(false);
                $jkValidation->setShowInputMessage(true);
                $jkValidation->setShowErrorMessage(true);
                $jkValidation->setShowDropDown(true);
                $jkValidation->setErrorTitle('Pilihan Jenis Kelamin');
                $jkValidation->setError('Pilih L (Laki-laki) atau P (Perempuan)');
                $jkValidation->setPromptTitle('Jenis Kelamin');
                $jkValidation->setPrompt('Pilih L atau P');
                $jkValidation->setFormula1('"L,P"');

                // Data validation for Agama (Column G)
                $agamaValidation = new DataValidation;
                $agamaValidation->setType(DataValidation::TYPE_LIST);
                $agamaValidation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                $agamaValidation->setAllowBlank(false);
                $agamaValidation->setShowInputMessage(true);
                $agamaValidation->setShowErrorMessage(true);
                $agamaValidation->setShowDropDown(true);
                $agamaValidation->setErrorTitle('Pilihan Agama');
                $agamaValidation->setError('Pilih salah satu agama yang tersedia');
                $agamaValidation->setPromptTitle('Agama');
                $agamaValidation->setPrompt('Pilih agama dari daftar');
                $agamaValidation->setFormula1('"Islam,Kristen,Katolik,Hindu,Buddha,Konghucu"');

                // Apply validations to rows 2 to 500
                for ($row = 2; $row <= 500; $row++) {
                    $sheet->getCell("D{$row}")->setDataValidation(clone $jkValidation);
                    $sheet->getCell("G{$row}")->setDataValidation(clone $agamaValidation);
                }
            },
        ];
    }
}
