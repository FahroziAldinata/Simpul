<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class TemplateSiswaPetunjukSheet implements FromArray, ShouldAutoSize, WithEvents, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'Petunjuk Pengisian';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Nama Kolom',
            'Wajib?',
            'Tipe / Format',
            'Keterangan & Contoh Valid',
        ];
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        return [
            [
                'NISN',
                'Wajib',
                'Teks (10 Digit)',
                'Nomor Induk Siswa Nasional, tepat 10 digit angka unik. Pastikan format sel adalah Teks agar angka nol di awal tidak hilang (contoh: 0081234567).',
            ],
            [
                'NIK',
                'Wajib',
                'Teks (16 Digit)',
                'Nomor Induk Kependudukan sesuai KK/KTP, tepat 16 digit angka (contoh: 3201012304080001).',
            ],
            [
                'Nama Lengkap',
                'Wajib',
                'Teks',
                'Nama lengkap siswa sesuai akta kelahiran. Sistem akan otomatis merapikan kapitalisasi (contoh: Muhammad Rizky Pratama).',
            ],
            [
                'Jenis Kelamin',
                'Wajib',
                'Pilihan (L/P)',
                'Gunakan pilihan dropdown: L untuk Laki-laki atau P untuk Perempuan.',
            ],
            [
                'Tempat Lahir',
                'Wajib',
                'Teks',
                'Nama kota/kabupaten tempat lahir (contoh: Bandung, Jakarta Selatan).',
            ],
            [
                'Tanggal Lahir',
                'Wajib',
                'Tanggal',
                'Format yang didukung: YYYY-MM-DD (2008-05-17) atau DD/MM/YYYY (17/05/2008).',
            ],
            [
                'Agama',
                'Wajib',
                'Pilihan',
                'Pilih dari dropdown: Islam, Kristen, Katolik, Hindu, Buddha, atau Konghucu.',
            ],
            [
                'Alamat',
                'Opsional',
                'Teks',
                'Alamat domisili lengkap siswa.',
            ],
            [
                'No. HP',
                'Opsional',
                'Teks',
                'Nomor telepon seluler/WhatsApp siswa atau orang tua.',
            ],
            [
                'Rombel',
                'Opsional',
                'Teks',
                'Nama rombel saat ini. Bersifat referensi tampilan di pratinjau; penempatan rombel dapat dilakukan secara massal setelah impor melalui menu Ubah Rombel.',
            ],
            [
                'CATATAN PENTING',
                'Info',
                '-',
                '1. Jangan ubah urutan atau nama kolom pada Sheet "Data Siswa".'.PHP_EOL.
                '2. Pengisian data dimulai dari baris ke-2.'.PHP_EOL.
                '3. Maksimal data dalam satu file adalah 5.000 baris.',
            ],
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

                $sheet->getStyle('A1:D1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                        'size' => 11,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '0F766E'], // Teal-700
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(26);

                $sheet->getStyle('A2:D12')->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'CBD5E1'],
                        ],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_TOP,
                    ],
                ]);

                // Highlight catatan penting row
                $sheet->getStyle('A12:D12')->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FEF3C7'], // Amber-100
                    ],
                    'font' => [
                        'bold' => true,
                    ],
                ]);
            },
        ];
    }
}
