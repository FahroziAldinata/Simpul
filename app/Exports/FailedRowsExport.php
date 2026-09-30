<?php

namespace App\Exports;

use App\Models\ImportBatch;
use App\Models\ImportRow;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * @implements WithMapping<ImportRow>
 */
class FailedRowsExport implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithMapping, WithStyles
{
    /** @var array<int, string> */
    protected array $rawHeaders = [];

    public function __construct(
        protected ImportBatch $batch
    ) {
        /** @var ImportRow|null $sampleRow */
        $sampleRow = $batch->rows()->first();
        if ($sampleRow && ! empty($sampleRow->data_mentah)) {
            $this->rawHeaders = array_keys($sampleRow->data_mentah);
        }
    }

    /**
     * @return Collection<int, ImportRow>
     */
    public function collection(): Collection
    {
        return $this->batch->rows()
            ->where('status', 'gagal')
            ->orderBy('nomor_baris')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        $headers = array_merge(['Nomor Baris'], $this->rawHeaders);
        $headers[] = 'Alasan Kegagalan / Error';

        return $headers;
    }

    /**
     * @param  ImportRow  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        $data = [$row->nomor_baris];

        $raw = $row->data_mentah ?? [];
        foreach ($this->rawHeaders as $header) {
            $data[] = $raw[$header] ?? '';
        }

        $errorText = '';
        if (! empty($row->errors)) {
            $messages = [];
            foreach ($row->errors as $field => $msg) {
                $messages[] = ucfirst((string) $field).': '.(string) $msg;
            }
            $errorText = implode(' | ', $messages);
        }

        $data[] = $errorText;

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '991B1B'], // Red-800
                ],
            ],
        ];
    }
}
