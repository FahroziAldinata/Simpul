<?php

namespace App\Services\Impor;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class RowNormalizer
{
    /**
     * Indonesian month names map.
     *
     * @var array<string, string>
     */
    protected const INDONESIAN_MONTHS = [
        'januari' => '01',
        'februari' => '02',
        'maret' => '03',
        'april' => '04',
        'mei' => '05',
        'juni' => '06',
        'juli' => '07',
        'agustus' => '08',
        'september' => '09',
        'oktober' => '10',
        'november' => '11',
        'desember' => '12',
        // Common short names
        'jan' => '01',
        'feb' => '02',
        'mar' => '03',
        'apr' => '04',
        'jun' => '06',
        'jul' => '07',
        'agu' => '08',
        'agt' => '08',
        'sep' => '09',
        'okt' => '10',
        'nov' => '11',
        'des' => '12',
    ];

    /**
     * Normalize a row of raw mapped student data.
     *
     * @param  array<string, mixed>  $rawRow  Keyed by system field names (nisn, nik, nama, ...)
     * @return array<string, mixed> Normalized clean data
     */
    public function normalize(array $rawRow): array
    {
        $clean = [];

        foreach ($rawRow as $field => $value) {
            $clean[$field] = match ($field) {
                'nisn' => $this->normalizeNisn($value),
                'nik' => $this->normalizeNik($value),
                'nama' => $this->normalizeNama($value),
                'jenis_kelamin' => $this->normalizeJenisKelamin($value),
                'tempat_lahir' => $this->normalizeTempatLahir($value),
                'tanggal_lahir' => $this->normalizeTanggalLahir($value),
                'agama' => $this->normalizeAgama($value),
                'alamat' => $this->normalizeAlamat($value),
                'no_hp' => $this->normalizeNoHp($value),
                'rombel' => $this->normalizeRombel($value),
                default => is_string($value) ? trim($value) : $value,
            };
        }

        return $clean;
    }

    /**
     * Anti-scientific notation and pad leading zero for NISN.
     */
    public function normalizeNisn(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $str = (string) $value;

        // If scientific notation e.g. 8.12345678E+09 or 8.12345678e9
        if (preg_match('/^([0-9.]+)[eE]\+?([0-9]+)$/', trim($str), $matches)) {
            $str = sprintf('%.0f', (float) $str);
        } elseif (is_numeric($str) && str_contains($str, '.')) {
            $str = sprintf('%.0f', (float) $str);
        }

        $digits = preg_replace('/[^0-9]/', '', $str) ?? '';

        if ($digits === '') {
            return null;
        }

        // If Excel truncated leading zero(s) (typically 8 or 9 digits instead of 10)
        if (strlen($digits) >= 8 && strlen($digits) < 10) {
            $digits = str_pad($digits, 10, '0', STR_PAD_LEFT);
        }

        return $digits;
    }

    /**
     * NIK: strip spaces, dashes, dots, ensure digits only.
     */
    public function normalizeNik(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $str = (string) $value;

        // Handle scientific notation if any
        if (preg_match('/^([0-9.]+)[eE]\+?([0-9]+)$/', trim($str))) {
            $str = sprintf('%.0f', (float) $str);
        }

        $digits = preg_replace('/[^0-9]/', '', $str) ?? '';

        return $digits !== '' ? $digits : null;
    }

    /**
     * Nama: Title Case and collapse multiple spaces.
     */
    public function normalizeNama(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $trimmed = trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');
        if ($trimmed === '') {
            return null;
        }

        return mb_convert_case($trimmed, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Jenis Kelamin: Map 'L' or 'P' from various textual inputs.
     */
    public function normalizeJenisKelamin(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $clean = mb_strtolower(trim((string) $value), 'UTF-8');
        $clean = str_replace([' ', '-', '_'], '', $clean);

        if (in_array($clean, ['l', 'lakilaki', 'laki', 'pria', 'm', 'male', '1'], true)) {
            return 'L';
        }

        if (in_array($clean, ['p', 'perempuan', 'wanita', 'f', 'female', '2'], true)) {
            return 'P';
        }

        return strtoupper(trim((string) $value));
    }

    /**
     * Tempat Lahir: Title Case and collapse whitespace.
     */
    public function normalizeTempatLahir(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $trimmed = trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');
        if ($trimmed === '') {
            return null;
        }

        return mb_convert_case($trimmed, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Tanggal Lahir: Support Excel serial, ISO, d/m/Y, d-m-Y, and Indonesian textual month.
     */
    public function normalizeTanggalLahir(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // 1. If Excel serial number (typically between 10000 and 80000 for realistic birth dates)
        if (is_numeric($value) && (float) $value > 1000 && (float) $value < 100000) {
            try {
                $dateTime = ExcelDate::excelToDateTimeObject((float) $value);

                return $dateTime->format('Y-m-d');
            } catch (Throwable) {
                // fall through
            }
        }

        $str = trim((string) $value);

        // 2. ISO format YYYY-MM-DD
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $str, $matches)) {
            $y = (int) $matches[1];
            $m = (int) $matches[2];
            $d = (int) $matches[3];
            if (checkdate($m, $d, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $m, $d);
            }
        }

        // 3. DD/MM/YYYY or DD-MM-YYYY
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $str, $matches)) {
            $d = (int) $matches[1];
            $m = (int) $matches[2];
            $y = (int) $matches[3];
            if (checkdate($m, $d, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $m, $d);
            }
        }

        // 4. Indonesian textual format: e.g. "17 Mei 2008", "17-Mei-2008", "17 Agustus 2007"
        if (preg_match('/^(\d{1,2})[\s\-]+([a-zA-Z]+)[\s\-]+(\d{4})$/', $str, $matches)) {
            $d = (int) $matches[1];
            $monthName = mb_strtolower($matches[2], 'UTF-8');
            $y = (int) $matches[3];

            if (isset(self::INDONESIAN_MONTHS[$monthName])) {
                $m = (int) self::INDONESIAN_MONTHS[$monthName];
                if (checkdate($m, $d, $y)) {
                    return sprintf('%04d-%02d-%02d', $y, $m, $d);
                }
            }
        }

        // 5. Try standard Carbon parse as a fallback
        try {
            $carbon = Carbon::parse($str);

            return $carbon->format('Y-m-d');
        } catch (Throwable) {
            return $str;
        }
    }

    /**
     * Agama: standard Indonesian religions.
     */
    public function normalizeAgama(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $clean = mb_strtolower(trim((string) $value), 'UTF-8');

        $map = [
            'islam' => 'Islam',
            'kristen' => 'Kristen',
            'protestan' => 'Kristen',
            'katolik' => 'Katolik',
            'catholic' => 'Katolik',
            'hindu' => 'Hindu',
            'buddha' => 'Buddha',
            'budha' => 'Buddha',
            'konghucu' => 'Konghucu',
            'khonghucu' => 'Konghucu',
        ];

        return $map[$clean] ?? mb_convert_case(trim((string) $value), MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Alamat: trim and collapse spaces.
     */
    public function normalizeAlamat(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $trimmed = trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * No HP: strip spaces, dashes, dots. Standardize +62 or 62 to 08.
     */
    public function normalizeNoHp(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $str = preg_replace('/[^0-9+]/', '', (string) $value) ?? '';

        if (str_starts_with($str, '+62')) {
            $remainder = substr($str, 3);
            $str = str_starts_with($remainder, '8') ? '0'.$remainder : '08'.$remainder;
        } elseif (str_starts_with($str, '62')) {
            $remainder = substr($str, 2);
            $str = str_starts_with($remainder, '8') ? '0'.$remainder : '08'.$remainder;
        }

        $digits = preg_replace('/[^0-9]/', '', $str) ?? '';

        return $digits !== '' ? $digits : null;
    }

    /**
     * Rombel: trim.
     */
    public function normalizeRombel(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
