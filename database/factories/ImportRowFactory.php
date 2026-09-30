<?php

namespace Database\Factories;

use App\Models\ImportBatch;
use App\Models\ImportRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportRow>
 */
class ImportRowFactory extends Factory
{
    protected $model = ImportRow::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'import_batch_id' => ImportBatch::factory(),
            'nomor_baris' => fake()->numberBetween(1, 100),
            'data_mentah' => ['NISN' => '0012345678', 'Nama' => 'Budi Santoso'],
            'data_bersih' => ['nisn' => '0012345678', 'nama' => 'Budi Santoso'],
            'status' => 'valid',
            'errors' => null,
            'aksi_duplikat' => null,
            'model_id' => null,
        ];
    }
}
