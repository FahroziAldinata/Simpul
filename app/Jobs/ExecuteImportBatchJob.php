<?php

namespace App\Jobs;

use App\Events\ImportProgressUpdated;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Siswa;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class ExecuteImportBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $sekolahId,
        public string $batchId
    ) {}

    public function handle(): void
    {
        /** @var ImportBatch|null $batch */
        $batch = ImportBatch::withoutGlobalScopes()
            ->where('sekolah_id', $this->sekolahId)
            ->find($this->batchId);

        if (! $batch || $batch->status === 'done') {
            return;
        }

        $batch->update(['status' => 'importing']);

        // Query only rows eligible for import (valid and peringatan)
        $rows = ImportRow::where('import_batch_id', $batch->id)
            ->whereIn('status', ['valid', 'peringatan'])
            ->orderBy('nomor_baris')
            ->get();

        $totalActionable = $rows->count();
        $chunks = $rows->chunk(200);

        $dibuatCount = 0;
        $diperbaruiCount = 0;
        $dilewatiCount = 0;
        $gagalChunkCount = 0;
        $processed = 0;

        foreach ($chunks as $chunk) {
            try {
                DB::transaction(function () use ($chunk, $batch, &$dibuatCount, &$diperbaruiCount, &$dilewatiCount): void {
                    foreach ($chunk as $row) {
                        $aksi = $row->aksi_duplikat;
                        /** @var array<string, mixed> $clean */
                        $clean = $row->data_bersih ?? [];

                        if ($aksi === 'lewati') {
                            $row->update(['status' => 'dilewati']);
                            $dilewatiCount++;

                            continue;
                        }

                        if ($aksi === 'perbarui') {
                            $targetModelId = $row->model_id ?? Siswa::withoutGlobalScopes()
                                ->where('sekolah_id', $this->sekolahId)
                                ->where('nisn', $clean['nisn'] ?? '')
                                ->value('id');

                            /** @var Siswa|null $existing */
                            $existing = $targetModelId ? Siswa::withoutGlobalScopes()
                                ->where('sekolah_id', $this->sekolahId)
                                ->find($targetModelId) : null;

                            if ($existing) {
                                $existing->update([
                                    'nama' => $clean['nama'] ?? $existing->nama,
                                    'nik' => $clean['nik'] ?? $existing->nik,
                                    'tempat_lahir' => $clean['tempat_lahir'] ?? $existing->tempat_lahir,
                                    'tanggal_lahir' => $clean['tanggal_lahir'] ?? $existing->tanggal_lahir,
                                    'agama' => $clean['agama'] ?? $existing->agama,
                                    'alamat' => $clean['alamat'] ?? $existing->alamat,
                                    'no_hp' => $clean['no_hp'] ?? $existing->no_hp,
                                ]);
                                $row->update(['status' => 'diimpor']);
                                $diperbaruiCount++;

                                continue;
                            }
                        }

                        // Default / new student: create new record
                        /** @var Siswa $siswa */
                        $siswa = Siswa::create([
                            'sekolah_id' => $this->sekolahId,
                            'nisn' => $clean['nisn'],
                            'nik' => $clean['nik'],
                            'nama' => $clean['nama'],
                            'jenis_kelamin' => $clean['jenis_kelamin'],
                            'tempat_lahir' => $clean['tempat_lahir'],
                            'tanggal_lahir' => $clean['tanggal_lahir'],
                            'agama' => $clean['agama'],
                            'alamat' => $clean['alamat'] ?? null,
                            'no_hp' => $clean['no_hp'] ?? null,
                            'status' => 'aktif',
                            'import_batch_id' => $batch->id,
                        ]);

                        $row->update([
                            'model_id' => $siswa->id,
                            'status' => 'diimpor',
                        ]);
                        $dibuatCount++;
                    }
                });
            } catch (Throwable $e) {
                // Per-chunk isolation: failure in this chunk does NOT abort remaining chunks
                $gagalChunkCount += count($chunk);
                foreach ($chunk as $row) {
                    $errors = $row->errors ?? [];
                    $errors['eksekusi'] = 'Gagal menyimpan transaksi chunk: '.$e->getMessage();
                    $row->update([
                        'status' => 'gagal',
                        'errors' => $errors,
                    ]);
                }
            }

            $processed += count($chunk);
            $pct = $totalActionable > 0 ? round(($processed / $totalActionable) * 100, 1) : 100.0;

            broadcast(new ImportProgressUpdated(
                sekolahId: $this->sekolahId,
                batchId: $batch->id,
                progress: [
                    'batch_id' => $batch->id,
                    'step' => 'importing',
                    'processed' => $processed,
                    'total' => $totalActionable,
                    'valid' => $dibuatCount,
                    'peringatan' => $diperbaruiCount,
                    'gagal' => $gagalChunkCount,
                    'percentage' => $pct,
                ]
            ));
        }

        $batch->update([
            'dibuat' => $dibuatCount,
            'diperbarui' => $diperbaruiCount,
            'dilewati' => $dilewatiCount,
            'status' => 'done',
            'dapat_dirollback_hingga' => now()->addHours(24),
        ]);

        broadcast(new ImportProgressUpdated(
            sekolahId: $this->sekolahId,
            batchId: $batch->id,
            progress: [
                'batch_id' => $batch->id,
                'step' => 'done',
                'processed' => $totalActionable,
                'total' => $totalActionable,
                'valid' => $dibuatCount,
                'peringatan' => $diperbaruiCount,
                'gagal' => $gagalChunkCount,
                'percentage' => 100.0,
            ]
        ));
    }
}
