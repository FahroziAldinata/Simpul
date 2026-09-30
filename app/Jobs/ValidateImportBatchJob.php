<?php

namespace App\Jobs;

use App\Events\ImportProgressUpdated;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Services\Impor\DuplicateDetector;
use App\Services\Impor\ExcelReaderService;
use App\Services\Impor\RowNormalizer;
use App\Services\Impor\RowValidator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ValidateImportBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  string  $sekolahId  Explicit school ID (Decision #1)
     * @param  string  $batchId  Import batch UUID
     * @param  int  $headerRowIndex  Row index of header in Excel
     * @param  string|null  $storageDisk  Storage disk where file is stored
     */
    public function __construct(
        public string $sekolahId,
        public string $batchId,
        public int $headerRowIndex = 1,
        public ?string $storageDisk = null
    ) {}

    public function handle(
        ExcelReaderService $reader,
        RowNormalizer $normalizer,
        DuplicateDetector $duplicateDetector,
        RowValidator $validator
    ): void {
        /** @var ImportBatch|null $batch */
        $batch = ImportBatch::withoutGlobalScopes()
            ->where('sekolah_id', $this->sekolahId)
            ->find($this->batchId);

        if (! $batch) {
            return;
        }

        $batch->update(['status' => 'validating']);

        $disk = $this->storageDisk ?? 'local';
        $fullPath = Storage::disk($disk)->path($batch->path);

        try {
            $mapping = $batch->pemetaan_kolom ?? [];
            $rawRows = $reader->readAllRows($fullPath, $this->headerRowIndex, $mapping);

            if (empty($rawRows)) {
                $batch->update([
                    'total_baris' => 0,
                    'valid' => 0,
                    'peringatan' => 0,
                    'gagal' => 0,
                    'status' => 'failed',
                ]);

                return;
            }

            // 1. Normalize all rows & collect NISNs
            $normalizedRows = [];
            $allNisns = [];

            foreach ($rawRows as $r) {
                $cleanData = $normalizer->normalize($r['mapped_data']);
                $normalizedRows[] = [
                    'nomor_baris' => $r['nomor_baris'],
                    'data_mentah' => $r['data_mentah'],
                    'data_bersih' => $cleanData,
                ];

                if (! empty($cleanData['nisn'])) {
                    $allNisns[] = (string) $cleanData['nisn'];
                }
            }

            // 2. Pre-compute in-file & database duplicates
            $inFiles = $duplicateDetector->detectInFileDuplicates($normalizedRows);
            $dbDups = $duplicateDetector->detectDatabaseDuplicates($this->sekolahId, array_values(array_unique($allNisns)));

            // 3. Clear previous rows if retrying validation
            ImportRow::where('import_batch_id', $batch->id)->delete();

            // 4. Process in chunks of 100 rows
            $total = count($normalizedRows);
            $chunks = array_chunk($normalizedRows, 100);
            $processedCount = 0;
            $validCount = 0;
            $peringatanCount = 0;
            $gagalCount = 0;

            foreach ($chunks as $chunk) {
                $rowsToInsert = [];

                foreach ($chunk as $row) {
                    $rowNum = $row['nomor_baris'];
                    $clean = $row['data_bersih'];
                    $nisn = $clean['nisn'] ?? null;

                    $inFileDup = $inFiles[$rowNum] ?? null;
                    $dbSameSchool = $nisn ? ($dbDups['same_school'][$nisn] ?? null) : null;
                    $dbOtherSchool = $nisn ? ($dbDups['other_school'][$nisn] ?? false) : false;

                    $validation = $validator->validateRow(
                        data: $clean,
                        inFileDuplicateWith: $inFileDup,
                        dbDuplicateSameSchool: $dbSameSchool,
                        dbDuplicateOtherSchool: $dbOtherSchool
                    );

                    if ($validation['status'] === 'valid') {
                        $validCount++;
                    } elseif ($validation['status'] === 'peringatan') {
                        $peringatanCount++;
                    } else {
                        $gagalCount++;
                    }

                    $errorsCombined = array_merge($validation['errors'], $validation['warnings']);

                    $rowsToInsert[] = [
                        'id' => (string) Str::uuid(),
                        'import_batch_id' => $batch->id,
                        'nomor_baris' => $rowNum,
                        'data_mentah' => json_encode($row['data_mentah'], JSON_THROW_ON_ERROR),
                        'data_bersih' => json_encode($clean, JSON_THROW_ON_ERROR),
                        'status' => $validation['status'],
                        'errors' => ! empty($errorsCombined) ? json_encode($errorsCombined, JSON_THROW_ON_ERROR) : null,
                        'aksi_duplikat' => $validation['aksi_duplikat'],
                        'model_id' => $validation['model_id'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $processedCount++;
                }

                ImportRow::insert($rowsToInsert);

                $percentage = round(($processedCount / $total) * 100, 1);
                broadcast(new ImportProgressUpdated(
                    sekolahId: $this->sekolahId,
                    batchId: $batch->id,
                    progress: [
                        'batch_id' => $batch->id,
                        'step' => 'validating',
                        'processed' => $processedCount,
                        'total' => $total,
                        'valid' => $validCount,
                        'peringatan' => $peringatanCount,
                        'gagal' => $gagalCount,
                        'percentage' => $percentage,
                    ]
                ));
            }

            // 5. Update batch state to preview
            $batch->update([
                'total_baris' => $total,
                'valid' => $validCount,
                'peringatan' => $peringatanCount,
                'gagal' => $gagalCount,
                'status' => 'preview',
            ]);

            broadcast(new ImportProgressUpdated(
                sekolahId: $this->sekolahId,
                batchId: $batch->id,
                progress: [
                    'batch_id' => $batch->id,
                    'step' => 'preview',
                    'processed' => $total,
                    'total' => $total,
                    'valid' => $validCount,
                    'peringatan' => $peringatanCount,
                    'gagal' => $gagalCount,
                    'percentage' => 100.0,
                ]
            ));
        } catch (Throwable $e) {
            $batch->update(['status' => 'failed']);

            broadcast(new ImportProgressUpdated(
                sekolahId: $this->sekolahId,
                batchId: $batch->id,
                progress: [
                    'batch_id' => $batch->id,
                    'step' => 'failed',
                    'processed' => 0,
                    'total' => 0,
                    'valid' => 0,
                    'peringatan' => 0,
                    'gagal' => 0,
                    'percentage' => 0.0,
                    'message' => $e->getMessage(),
                ]
            ));

            throw $e;
        }
    }
}
