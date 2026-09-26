<?php

namespace Database\Factories;

use App\Models\AlokasiJamMapel;
use App\Models\MataPelajaran;
use App\Models\Rombel;
use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlokasiJamMapel>
 */
class AlokasiJamMapelFactory extends Factory
{
    protected $model = AlokasiJamMapel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sekolah_id' => Sekolah::factory(),
            'rombel_id' => Rombel::factory(),
            'mata_pelajaran_id' => MataPelajaran::factory(),
            'guru_id' => null,
            'jam_per_minggu' => fake()->numberBetween(2, 5),
        ];
    }
}
