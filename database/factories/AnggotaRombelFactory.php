<?php

namespace Database\Factories;

use App\Models\AnggotaRombel;
use App\Models\Rombel;
use App\Models\Sekolah;
use App\Models\Semester;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnggotaRombel>
 */
class AnggotaRombelFactory extends Factory
{
    protected $model = AnggotaRombel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sekolah_id' => Sekolah::factory(),
            'rombel_id' => Rombel::factory(),
            'siswa_id' => Siswa::factory(),
            'semester_id' => Semester::factory(),
            'nomor_absen' => fake()->numberBetween(1, 36),
        ];
    }
}
