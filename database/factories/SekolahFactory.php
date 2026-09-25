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
        ];
    }
}
