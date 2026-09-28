<?php

namespace Database\Factories;

use App\Models\Sekolah;
use App\Models\Siswa;
use App\Models\WaliSiswa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaliSiswa>
 */
class WaliSiswaFactory extends Factory
{
    protected $model = WaliSiswa::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hubungan = fake()->randomElement(['ayah', 'ibu', 'wali']);

        return [
            'sekolah_id' => Sekolah::factory(),
            'siswa_id' => Siswa::factory(),
            'hubungan' => $hubungan,
            'nama' => fake()->name($hubungan === 'ibu' ? 'female' : 'male'),
            'pekerjaan' => fake()->jobTitle(),
            'no_hp' => '08'.fake()->numerify('##########'),
            'alamat' => fake()->address(),
        ];
    }
}
