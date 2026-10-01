<?php

namespace Database\Factories;

use App\Models\Sekolah;
use App\Models\TitikAbsen;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TitikAbsen>
 */
class TitikAbsenFactory extends Factory
{
    protected $model = TitikAbsen::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sekolah_id' => Sekolah::factory(),
            'nama' => 'Pintu Masuk '.fake()->unique()->word(),
            'secret' => bin2hex(random_bytes(32)),
            'latitude' => fake()->latitude(-6.3, -6.1),
            'longitude' => fake()->longitude(106.7, 106.9),
            'is_aktif' => true,
        ];
    }
}
