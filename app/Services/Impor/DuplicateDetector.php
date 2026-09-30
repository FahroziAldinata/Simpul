<?php

namespace App\Services\Impor;

use App\Models\Siswa;
use Illuminate\Support\Collection;

class DuplicateDetector
{
    /**
     * Detect duplicates within the import file rows.
     *
     * @param  array<int, array{nomor_baris: int, data_bersih: array<string, mixed>}>  $rows
     * @return array<int, array<int, int>> Map of nomor_baris => list of other row numbers sharing the same NISN
     */
    public function detectInFileDuplicates(array $rows): array
    {
        $nisnMap = []; // nisn => array of row numbers
        foreach ($rows as $row) {
            $nisn = $row['data_bersih']['nisn'] ?? null;
            if ($nisn !== null && $nisn !== '') {
                $nisnMap[$nisn][] = $row['nomor_baris'];
            }
        }

        $duplicates = [];
        foreach ($nisnMap as $nisn => $rowNumbers) {
            if (count($rowNumbers) > 1) {
                foreach ($rowNumbers as $rowNum) {
                    $otherRows = array_values(array_filter($rowNumbers, fn ($r) => $r !== $rowNum));
                    $duplicates[$rowNum] = $otherRows;
                }
            }
        }

        return $duplicates;
    }

    /**
     * Detect duplicates against the database.
     *
     * @param  string  $sekolahId  Current tenant school ID
     * @param  array<string>  $nisns  List of unique NISNs to check
     * @return array{
     *     same_school: array<string, array{id: string, nama: string, status: string}>,
     *     other_school: array<string, bool>
     * }
     */
    public function detectDatabaseDuplicates(string $sekolahId, array $nisns): array
    {
        if (empty($nisns)) {
            return [
                'same_school' => [],
                'other_school' => [],
            ];
        }

        // Query across all schools (withoutGlobalScopes) for active non-soft-deleted students
        /** @var Collection<int, Siswa> $existingStudents */
        $existingStudents = Siswa::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereIn('nisn', $nisns)
            ->get(['id', 'sekolah_id', 'nisn', 'nama', 'status']);

        $sameSchool = [];
        $otherSchool = [];

        foreach ($existingStudents as $student) {
            $nisn = (string) $student->nisn;
            if ((string) $student->sekolah_id === $sekolahId) {
                $sameSchool[$nisn] = [
                    'id' => (string) $student->id,
                    'nama' => (string) $student->nama,
                    'status' => $student->status->value,
                ];
            } else {
                // Other tenant: record flag only, DO NOT store PII or tenant name (Zero-leakage)
                $otherSchool[$nisn] = true;
            }
        }

        return [
            'same_school' => $sameSchool,
            'other_school' => $otherSchool,
        ];
    }
}
