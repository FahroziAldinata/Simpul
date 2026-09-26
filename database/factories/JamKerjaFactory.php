<?php

namespace Database\Factories;

use App\Models\JamKerja;
use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JamKerja>
 */
class JamKerjaFactory extends Factory
{
    protected $model = JamKerja::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sekolah_id' => Sekolah::factory(),
            'kelompok' => 'umum',
            'hari' => fake()->numberBetween(1, 6),
            'jam_masuk' => '07:00:00',
            'jam_pulang' => '15:00:00',
            'is_libur' => false,
            'jumlah_jam_pelajaran' => 8,
        ];
    }
}
