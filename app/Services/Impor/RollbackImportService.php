<?php

namespace App\Services\Impor;

use App\Models\AnggotaRombel;
use App\Models\BerkasSiswa;
use App\Models\ImportBatch;
use App\Models\MutasiSiswa;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

class RollbackImportService
{
    /**
     * Rollback an imported batch within 24 hours, guarding touched records (Decision #5).
     *
     * @return array{
     *     batch_id: string,
     *     dihapus: int,
     *     tersentuh_dilewati: int,
     *     detail_tersentuh: array<int, array{id: string, nisn: string, nama: string, alasan: string}>
     * }
     *
     * @throws ValidationException
     */
    public function rollback(ImportBatch $batch): array
    {
        if ($batch->status === 'rolled_back') {
            throw ValidationException::withMessages([
                'batch' => 'Batch impor ini sudah pernah dibatalkan sebelumnya.',
            ]);
        }

        if ($batch->status !== 'done') {
            throw ValidationException::withMessages([
                'batch' => 'Hanya batch impor yang berstatus selesai yang dapat dibatalkan.',
            ]);
        }

        if (! $batch->dapat_dirollback_hingga || now()->gt($batch->dapat_dirollback_hingga)) {
            throw ValidationException::withMessages([
                'batch' => 'Batas waktu pembatalan impor (24 jam sejak impor selesai) telah berakhir.',
            ]);
        }

        return DB::transaction(function () use ($batch) {
            /** @var Collection<int, Siswa> $students */
            $students = Siswa::withoutGlobalScopes()
                ->where('sekolah_id', $batch->sekolah_id)
                ->where('import_batch_id', $batch->id)
                ->get();

            $deletedCount = 0;
            $touchedRecords = [];

            foreach ($students as $student) {
                $touchReason = $this->getTouchReason($student, $batch);

                if ($touchReason !== null) {
                    $touchedRecords[] = [
                        'id' => (string) $student->id,
                        'nisn' => (string) $student->nisn,
                        'nama' => (string) $student->nama,
                        'alasan' => $touchReason,
                    ];
                } else {
                    // Soft delete student as per PRD Rule D3
                    $student->delete();
                    $deletedCount++;
                }
            }

            $batch->update([
                'status' => 'rolled_back',
            ]);

            return [
                'batch_id' => (string) $batch->id,
                'dihapus' => $deletedCount,
                'tersentuh_dilewati' => count($touchedRecords),
                'detail_tersentuh' => $touchedRecords,
            ];
        });
    }

    /**
     * Check if a student record is touched after import time (Decision #5).
     */
    protected function getTouchReason(Siswa $student, ImportBatch $batch): ?string
    {
        // 1. Memiliki penempatan anggota rombel
        if (AnggotaRombel::where('siswa_id', $student->id)->exists()) {
            return 'Siswa telah dimasukkan ke dalam rombongan belajar (anggota_rombel).';
        }

        // 2. Memiliki mutasi baru setelah import batch dibuat
        if (MutasiSiswa::where('siswa_id', $student->id)->where('created_at', '>', $batch->created_at)->exists()) {
            return 'Siswa memiliki riwayat mutasi baru setelah waktu impor.';
        }

        // 3. Memiliki berkas dokumen siswa
        if (BerkasSiswa::where('siswa_id', $student->id)->exists()) {
            return 'Siswa telah memiliki unggahan berkas dokumen.';
        }

        // 4. Ada entri activity_log bertipe 'updated' pada Siswa setelah waktu impor
        $hasManualEdit = Activity::forSubject($student)
            ->where('description', 'updated')
            ->where('created_at', '>', $batch->created_at)
            ->exists();

        if ($hasManualEdit) {
            return 'Data siswa telah disunting manual setelah waktu impor.';
        }

        return null;
    }
}
