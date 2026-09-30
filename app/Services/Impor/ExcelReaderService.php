<?php

namespace App\Services\Impor;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class ExcelReaderService
{
    public function __construct(
        protected ColumnMapper $columnMapper
    ) {}

    /**
     * Parse preview: header row, sample 20 rows, and total row count.
     *
     * @return array{
     *     header_row_index: int,
     *     headers: array<int, string>,
     *     predicted_mapping: array<string, string|null>,
     *     sample_rows: array<int, array<string, mixed>>,
     *     total_rows: int
     * }
     */
    public function parsePreview(string $filePath): array
    {
        $spreadsheet = $this->loadSpreadsheet($filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        // Find header row: inspect first 5 rows to find the one with the most non-empty string headers
        $headerRowIndex = $this->detectHeaderRow($worksheet, min(5, $highestRow), $highestColumnIndex);

        // Extract headers
        $headers = [];
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $val = trim((string) $worksheet->getCell("{$colLetter}{$headerRowIndex}")->getValue());
            if ($val !== '') {
                $headers[$col] = $val;
            }
        }

        if (empty($headers)) {
            throw new RuntimeException('Tidak dapat menemukan baris header pada file Excel.');
        }

        // Count total data rows (non-empty rows after header)
        $totalDataRows = 0;
        $sampleRows = [];

        for ($row = $headerRowIndex + 1; $row <= $highestRow; $row++) {
            $rowData = [];
            $hasData = false;

            foreach ($headers as $col => $headerName) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $cell = $worksheet->getCell("{$colLetter}{$row}");
                $val = $cell->getCalculatedValue();

                if ($val !== null && trim((string) $val) !== '') {
                    $hasData = true;
                }
                $rowData[$headerName] = $val;
            }

            if ($hasData) {
                $totalDataRows++;
                if (count($sampleRows) < 20) {
                    $sampleRows[] = [
                        'nomor_baris' => $row,
                        'data' => $rowData,
                    ];
                }
            }
        }

        $headerValues = array_values($headers);
        $predictedMapping = $this->columnMapper->predict($headerValues);

        return [
            'header_row_index' => $headerRowIndex,
            'headers' => $headerValues,
            'predicted_mapping' => $predictedMapping,
            'sample_rows' => $sampleRows,
            'total_rows' => $totalDataRows,
        ];
    }

    /**
     * Read all data rows from the Excel file starting after header row.
     *
     * @param  array<string, string>  $mapping  Map of Excel header => system field
     * @return array<int, array{nomor_baris: int, data_mentah: array<string, mixed>, mapped_data: array<string, mixed>}>
     */
    public function readAllRows(string $filePath, int $headerRowIndex, array $mapping): array
    {
        $spreadsheet = $this->loadSpreadsheet($filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        // Map column index to header name
        $headerMap = [];
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $val = trim((string) $worksheet->getCell("{$colLetter}{$headerRowIndex}")->getValue());
            if ($val !== '') {
                $headerMap[$col] = $val;
            }
        }

        $rows = [];

        for ($row = $headerRowIndex + 1; $row <= $highestRow; $row++) {
            $dataMentah = [];
            $mappedData = [];
            $hasData = false;

            foreach ($headerMap as $col => $headerName) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $cell = $worksheet->getCell("{$colLetter}{$row}");
                $val = $cell->getCalculatedValue();

                if ($val !== null && trim((string) $val) !== '') {
                    $hasData = true;
                }

                $dataMentah[$headerName] = $val;

                if (isset($mapping[$headerName])) {
                    $systemField = $mapping[$headerName];
                    $mappedData[$systemField] = $val;
                }
            }

            if ($hasData) {
                $rows[] = [
                    'nomor_baris' => $row,
                    'data_mentah' => $dataMentah,
                    'mapped_data' => $mappedData,
                ];
            }
        }

        return $rows;
    }

    /**
     * Detect header row by checking which of the first few rows has the highest number of recognizable headers.
     */
    protected function detectHeaderRow(Worksheet $worksheet, int $maxSearchRows, int $highestColumnIndex): int
    {
        $bestRow = 1;
        $maxRecognized = 0;

        for ($row = 1; $row <= $maxSearchRows; $row++) {
            $recognizedCount = 0;
            $nonEmptyCount = 0;

            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $val = trim((string) $worksheet->getCell("{$colLetter}{$row}")->getValue());

                if ($val !== '') {
                    $nonEmptyCount++;
                    if ($this->columnMapper->matchSingleHeader($val) !== null) {
                        $recognizedCount++;
                    }
                }
            }

            // If this row has more recognized headers, select it
            if ($recognizedCount > $maxRecognized && $nonEmptyCount >= 3) {
                $maxRecognized = $recognizedCount;
                $bestRow = $row;
            }
        }

        return $bestRow;
    }

    /**
     * Load spreadsheet with read-data-only to save memory.
     */
    protected function loadSpreadsheet(string $filePath): Spreadsheet
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException("File tidak ditemukan: {$filePath}");
        }

        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);

        return $reader->load($filePath);
    }
}
