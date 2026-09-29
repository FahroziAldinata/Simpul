<?php

namespace App\Exports;

use App\Enums\JenisKelamin;
use App\Models\Siswa;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * @implements WithMapping<Siswa>
 */
class SiswaExport extends DefaultValueBinder implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithCustomValueBinder, WithHeadings, WithMapping
{
    protected int $rowIndex = 0;

    /**
     * @param  Collection<int, Siswa>  $siswa
     */
    public function __construct(
        protected Collection $siswa
    ) {}

    /**
     * @return Collection<int, Siswa>
     */
    public function collection(): Collection
    {
        return $this->siswa;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'No',
            'NISN',
            'NIK',
            'Nama Siswa',
            'Jenis Kelamin',
            'Tempat Lahir',
            'Tanggal Lahir',
            'Agama',
            'Alamat',
            'No. HP',
            'Status',
            'Rombel',
        ];
    }

    /**
     * @param  Siswa  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        $this->rowIndex++;

        $anggotaAktif = $row->anggotaRombelAktif;
        $rombelNama = $anggotaAktif?->rombel->nama ?? '-';

        return [
            $this->rowIndex,
            $this->sanitizeCell((string) $row->nisn),
            $this->sanitizeCell((string) $row->nik),
            $this->sanitizeCell((string) $row->nama),
            $this->sanitizeCell($row->jenis_kelamin === JenisKelamin::L ? 'Laki-laki' : 'Perempuan'),
            $this->sanitizeCell((string) $row->tempat_lahir),
            $this->sanitizeCell($row->tanggal_lahir->format('d/m/Y')),
            $this->sanitizeCell((string) $row->agama),
            $this->sanitizeCell((string) ($row->alamat ?? '')),
            $this->sanitizeCell((string) ($row->no_hp ?? '')),
            $this->sanitizeCell(ucfirst($row->status->value)),
            $this->sanitizeCell((string) $rombelNama),
        ];
    }

    /**
     * Explicit column formatting for text representations to prevent scientific notation.
     *
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_TEXT, // NISN
            'C' => NumberFormat::FORMAT_TEXT, // NIK
            'J' => NumberFormat::FORMAT_TEXT, // No. HP
        ];
    }

    /**
     * Explicit value binding to guarantee strings for long numeric identifiers.
     */
    public function bindValue(Cell $cell, mixed $value): bool
    {
        $column = $cell->getColumn();

        // Columns B (NISN), C (NIK), J (No HP) must always be bound as explicit String type
        if (in_array($column, ['B', 'C', 'J'], true)) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    /**
     * Sanitize formula injection: prefix cell with single quote (') if starting with =, +, -, @
     */
    protected function sanitizeCell(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        if (in_array(substr($value, 0, 1), ['=', '+', '-', '@'], true)) {
            return "'".$value;
        }

        return $value;
    }
}
