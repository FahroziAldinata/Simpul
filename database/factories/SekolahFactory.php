<?php

namespace Database\Factories;

use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sekolah>
 */
class SekolahFactory extends Factory
{
    protected $model = Sekolah::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'npsn' => (string) fake()->unique()->numerify('########'),
            'nama' => 'SMA Negeri '.fake()->city(),
            'jenjang' => fake()->randomElement(['sd', 'smp', 'sma', 'smk']),
            'status' => fake()->randomElement(['negeri', 'swasta']),
            'alamat' => fake()->address(),
            'latitude' => fake()->latitude(-8.5, -6.0),
            'longitude' => fake()->longitude(106.0, 114.0),
            'radius_absen_meter' => 150,
            'logo_path' => null,
            'kepala_sekolah' => fake()->name(),
            'akreditasi' => fake()->randomElement(['A', 'B', 'C', 'Belum']),
        ];
    }
}
