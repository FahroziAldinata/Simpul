<?php

namespace App\Services\Impor;

class ColumnMapper
{
    /**
     * Known system fields for student import with their synonyms and descriptions.
     *
     * @var array<string, array{label: string, required: bool, synonyms: array<string>}>
     */
    public const SYSTEM_FIELDS = [
        'nisn' => [
            'label' => 'NISN',
            'required' => true,
            'synonyms' => [
                'nisn', 'no nisn', 'nomor nisn', 'nis n', 'no_nisn', 'nomor_nisn',
                'nomor induk siswa nasional', 'nomor_induk_siswa_nasional', 'nisn siswa', 'nisn_siswa',
            ],
        ],
        'nik' => [
            'label' => 'NIK',
            'required' => true,
            'synonyms' => [
                'nik', 'no nik', 'nomor nik', 'no_nik', 'nik siswa', 'nik_siswa',
                'nomor induk kependudukan', 'nomor_induk_kependudukan', 'ktp', 'no ktp',
                'nik/no.kk', 'nik/kk', 'no kk', 'nomor kk',
            ],
        ],
        'nama' => [
            'label' => 'Nama Lengkap',
            'required' => true,
            'synonyms' => [
                'nama', 'nama lengkap', 'nama_lengkap', 'nama siswa', 'nama_siswa',
                'nama peserta didik', 'nama_peserta_didik', 'nama murid', 'nama_murid',
                'student name', 'student_name', 'full name', 'full_name', 'fullname',
            ],
        ],
        'jenis_kelamin' => [
            'label' => 'Jenis Kelamin',
            'required' => true,
            'synonyms' => [
                'jenis kelamin', 'jenis_kelamin', 'jk', 'gender', 'sex', 'kelamin',
                'jns kelamin', 'jns_kelamin', 'l/p', 'lp',
            ],
        ],
        'tempat_lahir' => [
            'label' => 'Tempat Lahir',
            'required' => true,
            'synonyms' => [
                'tempat lahir', 'tempat_lahir', 'tpt lahir', 'tpt_lahir', 'kota lahir',
                'kota_lahir', 'tmp lahir', 'tmp_lahir', 't. lahir', 't lahir',
            ],
        ],
        'tanggal_lahir' => [
            'label' => 'Tanggal Lahir',
            'required' => true,
            'synonyms' => [
                'tanggal lahir', 'tanggal_lahir', 'tgl lahir', 'tgl_lahir', 'birth date',
                'birth_date', 'birthdate', 'dob', 'date of birth', 'tgl',
            ],
        ],
        'agama' => [
            'label' => 'Agama',
            'required' => true,
            'synonyms' => [
                'agama', 'religion', 'keyakinan',
            ],
        ],
        'alamat' => [
            'label' => 'Alamat',
            'required' => false,
            'synonyms' => [
                'alamat', 'alamat lengkap', 'alamat_lengkap', 'domisili', 'alamat domisili',
                'alamat tinggal', 'tempat tinggal', 'address', 'alamat siswa', 'alamat_siswa',
            ],
        ],
        'no_hp' => [
            'label' => 'No. HP',
            'required' => false,
            'synonyms' => [
                'no hp', 'no_hp', 'nohp', 'nomor hp', 'nomor_hp', 'telepon', 'no telepon',
                'nomor telepon', 'no telp', 'no_telp', 'wa', 'no wa', 'no_wa', 'whatsapp',
                'handphone', 'phone',
            ],
        ],
        'rombel' => [
            'label' => 'Rombel / Kelas',
            'required' => false,
            'synonyms' => [
                'rombel', 'kelas', 'nama rombel', 'nama_rombel', 'rombongan belajar',
                'rombongan_belajar', 'kelas saat ini', 'rombel saat ini', 'tingkat/rombel',
            ],
        ],
    ];

    /**
     * Predict system field for a list of Excel header names.
     * Returns an associative array: ['excel_header' => 'system_field_or_null'].
     *
     * @param  array<int, string>  $headers
     * @return array<string, string|null>
     */
    public function predict(array $headers): array
    {
        $mapping = [];
        $usedSystemFields = [];

        foreach ($headers as $header) {
            $matchedField = $this->matchSingleHeader($header, $usedSystemFields);
            $mapping[$header] = $matchedField;
            if ($matchedField !== null) {
                $usedSystemFields[] = $matchedField;
            }
        }

        return $mapping;
    }

    /**
     * Match a single header against system fields using exact synonym match and Levenshtein similarity.
     *
     * @param  array<string>  $alreadyAssigned
     */
    public function matchSingleHeader(string $header, array $alreadyAssigned = []): ?string
    {
        $normalizedHeader = $this->cleanHeader($header);

        if ($normalizedHeader === '') {
            return null;
        }

        // 1. Exact synonym matching
        foreach (self::SYSTEM_FIELDS as $systemField => $config) {
            if (in_array($systemField, $alreadyAssigned, true)) {
                continue;
            }

            foreach ($config['synonyms'] as $synonym) {
                if ($normalizedHeader === $this->cleanHeader($synonym)) {
                    return $systemField;
                }
            }
        }

        // 2. Substring matching (e.g. "NISN Siswa 2026" contains "nisn")
        foreach (self::SYSTEM_FIELDS as $systemField => $config) {
            if (in_array($systemField, $alreadyAssigned, true)) {
                continue;
            }

            foreach ($config['synonyms'] as $synonym) {
                $cleanSynonym = $this->cleanHeader($synonym);
                // Only if synonym has meaningful length (> 2 chars, to avoid false positives like 'wa' or 'jk')
                if (strlen($cleanSynonym) >= 4 && str_contains($normalizedHeader, $cleanSynonym)) {
                    return $systemField;
                }
            }
        }

        // 3. Levenshtein distance matching
        $bestMatch = null;
        $highestSimilarity = 0.0;

        foreach (self::SYSTEM_FIELDS as $systemField => $config) {
            if (in_array($systemField, $alreadyAssigned, true)) {
                continue;
            }

            foreach ($config['synonyms'] as $synonym) {
                $cleanSynonym = $this->cleanHeader($synonym);
                $sim = $this->calculateSimilarity($normalizedHeader, $cleanSynonym);

                if ($sim > $highestSimilarity) {
                    $highestSimilarity = $sim;
                    $bestMatch = $systemField;
                }
            }
        }

        // Accept match if similarity is at least 75%
        if ($highestSimilarity >= 0.75) {
            return $bestMatch;
        }

        return null;
    }

    /**
     * Clean header: lowercase, trim, strip punctuation and non-alphanumeric except spaces.
     */
    protected function cleanHeader(string $header): string
    {
        $header = mb_strtolower(trim($header), 'UTF-8');
        // Replace underscores, slashes, dashes with spaces
        $header = str_replace(['_', '/', '-', '.'], ' ', $header);

        // Replace multiple spaces with a single space
        return preg_replace('/\s+/', ' ', $header) ?? '';
    }

    /**
     * Calculate similarity between two strings using Levenshtein distance.
     * Returns a float between 0.0 and 1.0.
     */
    protected function calculateSimilarity(string $str1, string $str2): float
    {
        $len1 = strlen($str1);
        $len2 = strlen($str2);

        if ($len1 === 0 && $len2 === 0) {
            return 1.0;
        }

        if ($len1 === 0 || $len2 === 0) {
            return 0.0;
        }

        $distance = levenshtein($str1, $str2);
        $maxLen = max($len1, $len2);

        return 1.0 - ($distance / $maxLen);
    }
}
