<?php

namespace Database\Factories;

use App\Models\ImportBatch;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportBatch>
 */
class ImportBatchFactory extends Factory
{
    protected $model = ImportBatch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sekolah_id' => Sekolah::factory(),
            'user_id' => User::factory(),
            'tipe' => 'siswa',
            'nama_file' => 'siswa.xlsx',
            'path' => 'imports/siswa.xlsx',
            'pemetaan_kolom' => null,
            'total_baris' => 0,
            'valid' => 0,
            'peringatan' => 0,
            'gagal' => 0,
            'dibuat' => 0,
            'diperbarui' => 0,
            'dilewati' => 0,
            'status' => 'uploaded',
            'dapat_dirollback_hingga' => null,
        ];
    }
}
