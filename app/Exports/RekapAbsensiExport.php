<?php

namespace App\Exports;

use App\Models\Sekolah;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * RekapAbsensiExport — ekspor Excel rekap bulanan (T-09.08).
 *
 * Format disintesis karena riset lapangan Minggu 1 dilewati (ADR-0000).
 * Format mengikuti konvensi umum laporan absensi sekolah Indonesia:
 *  - Kop surat sekolah (nama, NPSN, alamat)
 *  - Sub-header: REKAP ABSENSI PEGAWAI — Bulan/Tahun
 *  - Tabel: No | Nama | NIP | Jenis | H | T | S | I | C | D | A | Menit Terlambat
 *  - Footer: blok tanda tangan Kepala Sekolah
 *
 * NIP disimpan sebagai teks (DataType::TYPE_STRING) agar tidak jadi notasi ilmiah.
 */
class RekapAbsensiExport implements FromArray, WithEvents, WithStyles
{
    /**
     * @param array{
     *     bulan: int,
     *     tahun: int,
     *     hari_dalam_bulan: int,
     *     baris: array<int, array{
     *         pegawai_id: string,
     *         nama: string,
     *         nip: string|null,
     *         jenis: string,
     *         sel: array<string, array{label: string, warna: string, menit: int}|null>,
     *         ringkasan: array{H: int, T: int, I: int, S: int, C: int, D: int, A: int, total_menit: int},
     *     }>,
     *     durasi_ms: int,
     * } $rekap
     */
    public function __construct(
        private readonly array $rekap,
        private readonly ?Sekolah $sekolah,
        private readonly int $bulan,
        private readonly int $tahun,
    ) {}

    /** @return array<int, array<int, mixed>> */
    public function array(): array
    {
        $rows = [];

        // Baris 1–2: Kop sekolah
        $rows[] = [$this->sekolah !== null ? $this->sekolah->nama : 'Sekolah'];
        $rows[] = ['NPSN: '.($this->sekolah !== null ? $this->sekolah->npsn : '-').' | '.($this->sekolah !== null ? $this->sekolah->alamat : '')];

        // Baris 3: kosong
        $rows[] = [];

        // Baris 4: Judul
        $namaBulan = $this->namaBulan($this->bulan);
        $rows[] = ['REKAP ABSENSI PEGAWAI — '.$namaBulan.' '.$this->tahun];

        // Baris 5: kosong
        $rows[] = [];

        // Baris 6: Header kolom
        $rows[] = ['No', 'Nama Pegawai', 'NIP', 'Jenis', 'H', 'T', 'S', 'I', 'C', 'D', 'A', 'Menit Terlambat'];

        // Baris data
        foreach ($this->rekap['baris'] as $i => $baris) {
            $rows[] = [
                $i + 1,
                $baris['nama'],
                "\t".($baris['nip'] ?? ''),  // Prefix tab memaksa Excel memperlakukan sebagai teks
                $baris['jenis'],
                $baris['ringkasan']['H'],
                $baris['ringkasan']['T'],
                $baris['ringkasan']['S'],
                $baris['ringkasan']['I'],
                $baris['ringkasan']['C'],
                $baris['ringkasan']['D'],
                $baris['ringkasan']['A'],
                $baris['ringkasan']['total_menit'],
            ];
        }

        // Baris kosong setelah data
        $rows[] = [];

        // Footer tanda tangan
        $rows[] = ['', '', '', '', '', '', '', '', '', '', 'Mengetahui,'];
        $rows[] = ['', '', '', '', '', '', '', '', '', '', 'Kepala Sekolah'];
        $rows[] = [];
        $rows[] = [];
        $rows[] = [];
        $rows[] = ['', '', '', '', '', '', '', '', '', '', '(________________________)'];

        return $rows;
    }

    /** @return array<string, mixed> */
    public function styles(Worksheet $sheet): array
    {
        // Merge kop
        $sheet->mergeCells('A1:L1');
        $sheet->mergeCells('A2:L2');
        $sheet->mergeCells('A4:L4');

        // Gaya header
        $sheet->getStyle('A6:L6')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D1FAE5'],
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Gaya sel data
        $dataStart = 7;
        $dataEnd = $dataStart + count($this->rekap['baris']) - 1;
        if ($dataEnd >= $dataStart) {
            $sheet->getStyle("A{$dataStart}:L{$dataEnd}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);
        }

        // Kolom NIP (C) sebagai teks — mencegah notasi ilmiah (pola Minggu 6)
        $sheet->getStyle('C7:C'.$dataEnd)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        return [];
    }

    /** @return array<string, \Closure> */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->getColumnDimension('A')->setWidth(5);
                $event->sheet->getDelegate()->getColumnDimension('B')->setWidth(30);
                $event->sheet->getDelegate()->getColumnDimension('C')->setWidth(20);
                $event->sheet->getDelegate()->getColumnDimension('D')->setWidth(10);
                foreach (['E', 'F', 'G', 'H', 'I', 'J', 'K'] as $col) {
                    $event->sheet->getDelegate()->getColumnDimension($col)->setWidth(6);
                }
                $event->sheet->getDelegate()->getColumnDimension('L')->setWidth(16);
            },
        ];
    }

    private function namaBulan(int $bulan): string
    {
        return [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ][$bulan] ?? '-';
    }
}
