<?php

namespace App\Services\Impor;

use Carbon\Carbon;
use Throwable;

class RowValidator
{
    public const VALID_RELIGIONS = [
        'Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu',
    ];

    /**
     * Validate a normalized row.
     *
     * @param  array<string, mixed>  $data  Normalized row data
     * @param  array<int, int>|null  $inFileDuplicateWith  Other row numbers with same NISN in file
     * @param  array{id: string, nama: string, status: string}|null  $dbDuplicateSameSchool  Student info if duplicate in same school
     * @param  bool  $dbDuplicateOtherSchool  True if duplicate in other tenant school
     * @return array{
     *     status: 'valid'|'peringatan'|'gagal',
     *     errors: array<string, string>,
     *     warnings: array<string, string>,
     *     aksi_duplikat: string|null,
     *     model_id: string|null
     * }
     */
    public function validateRow(
        array $data,
        ?array $inFileDuplicateWith = null,
        ?array $dbDuplicateSameSchool = null,
        bool $dbDuplicateOtherSchool = false
    ): array {
        $errors = [];
        $warnings = [];
        $aksiDuplikat = null;
        $modelId = null;

        // 1. NISN Validation
        $nisn = $data['nisn'] ?? null;
        if (empty($nisn)) {
            $errors['nisn'] = 'NISN wajib diisi.';
        } elseif (! preg_match('/^[0-9]{10}$/', (string) $nisn)) {
            $errors['nisn'] = 'NISN harus berupa 10 digit angka.';
        } elseif (! empty($inFileDuplicateWith)) {
            $errors['nisn'] = 'Duplikat di dalam file dengan baris '.implode(', ', $inFileDuplicateWith).'.';
        } elseif ($dbDuplicateOtherSchool) {
            // Neutral message without leaking other tenant info
            $errors['nisn'] = 'NISN sudah terdaftar di sistem. Hubungi Super Admin untuk verifikasi lebih lanjut.';
        } elseif ($dbDuplicateSameSchool !== null) {
            $warnings['nisn'] = "NISN sudah terdaftar pada siswa {$dbDuplicateSameSchool['nama']} di sekolah ini.";
            $aksiDuplikat = 'lewati';
            $modelId = $dbDuplicateSameSchool['id'];
        }

        // 2. NIK Validation & Cross Check with Birth Date
        $nik = $data['nik'] ?? null;
        $nikValid = false;
        if (empty($nik)) {
            $errors['nik'] = 'NIK wajib diisi.';
        } elseif (! preg_match('/^[0-9]{16}$/', (string) $nik)) {
            $errors['nik'] = 'NIK harus berupa 16 digit angka.';
        } else {
            $nikValid = true;
        }

        // 3. Nama Validation
        $nama = $data['nama'] ?? null;
        if (empty($nama)) {
            $errors['nama'] = 'Nama lengkap wajib diisi.';
        } elseif (mb_strlen((string) $nama, 'UTF-8') < 2) {
            $errors['nama'] = 'Nama lengkap minimal 2 karakter.';
        }

        // 4. Jenis Kelamin Validation
        $jk = $data['jenis_kelamin'] ?? null;
        if (empty($jk)) {
            $errors['jenis_kelamin'] = 'Jenis kelamin wajib diisi.';
        } elseif (! in_array($jk, ['L', 'P'], true)) {
            $errors['jenis_kelamin'] = "Jenis kelamin harus 'L' (Laki-laki) atau 'P' (Perempuan).";
        }

        // 5. Tempat Lahir Validation
        $tempatLahir = $data['tempat_lahir'] ?? null;
        if (empty($tempatLahir)) {
            $errors['tempat_lahir'] = 'Tempat lahir wajib diisi.';
        }

        // 6. Tanggal Lahir Validation
        $tglLahir = $data['tanggal_lahir'] ?? null;
        $tglLahirValid = false;
        if (empty($tglLahir)) {
            $errors['tanggal_lahir'] = 'Tanggal lahir wajib diisi.';
        } else {
            try {
                $carbonTgl = Carbon::createFromFormat('Y-m-d', (string) $tglLahir);
                if ($carbonTgl === null || $carbonTgl->format('Y-m-d') !== (string) $tglLahir) {
                    $errors['tanggal_lahir'] = 'Format tanggal lahir tidak valid (gunakan YYYY-MM-DD atau DD/MM/YYYY).';
                } elseif ($carbonTgl->isFuture() || $carbonTgl->isToday()) {
                    $errors['tanggal_lahir'] = 'Tanggal lahir harus sebelum hari ini.';
                } elseif ($carbonTgl->year < 1980) {
                    $errors['tanggal_lahir'] = 'Tahun lahir tidak masuk akal (kurang dari 1980).';
                } else {
                    $tglLahirValid = true;
                }
            } catch (Throwable) {
                $errors['tanggal_lahir'] = 'Format tanggal lahir tidak valid.';
            }
        }

        // Cross-check NIK with birth date (warning only, per PRD US-12 AC4)
        if ($nikValid && $tglLahirValid && in_array($jk, ['L', 'P'], true)) {
            $nikStr = (string) $nik;
            $nikDD = (int) substr($nikStr, 6, 2);
            $nikMM = (int) substr($nikStr, 8, 2);
            $nikYY = (int) substr($nikStr, 10, 2);

            $expectedDD = $jk === 'P' ? ($nikDD > 40 ? $nikDD - 40 : $nikDD) : $nikDD;
            $parts = explode('-', (string) $tglLahir);
            $actualY = (int) $parts[0];
            $actualM = (int) $parts[1];
            $actualD = (int) $parts[2];

            if ($expectedDD !== $actualD || $nikMM !== $actualM || $nikYY !== (int) substr((string) $actualY, 2, 2)) {
                $warnings['nik'] = 'Tanggal lahir tidak cocok dengan digit NIK ke-7 s.d. 12 ('.substr($nikStr, 6, 6).').';
            }
        }

        // 7. Agama Validation
        $agama = $data['agama'] ?? null;
        if (empty($agama)) {
            $errors['agama'] = 'Agama wajib diisi.';
        } elseif (! in_array($agama, self::VALID_RELIGIONS, true)) {
            $errors['agama'] = 'Agama harus salah satu dari: '.implode(', ', self::VALID_RELIGIONS).'.';
        }

        // Determine Status
        if (! empty($errors)) {
            $status = 'gagal';
        } elseif (! empty($warnings)) {
            $status = 'peringatan';
        } else {
            $status = 'valid';
        }

        return [
            'status' => $status,
            'errors' => $errors,
            'warnings' => $warnings,
            'aksi_duplikat' => $aksiDuplikat,
            'model_id' => $modelId,
        ];
    }
}
