<?php

namespace Database\Factories;

use App\Models\Sekolah;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Siswa>
 */
class SiswaFactory extends Factory
{
    protected $model = Siswa::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = fake()->randomElement(['L', 'P']);
        $birthDate = fake()->dateTimeBetween('-18 years', '-12 years');

        return [
            'sekolah_id' => Sekolah::factory(),
            'nisn' => fake()->unique()->numerify('00########'),
            'nik' => fake()->numerify('320101######0001'),
            'nama' => fake()->name($gender === 'L' ? 'male' : 'female'),
            'jenis_kelamin' => $gender,
            'tempat_lahir' => fake()->city(),
            'tanggal_lahir' => $birthDate->format('Y-m-d'),
            'agama' => fake()->randomElement(['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu']),
            'alamat' => fake()->address(),
            'no_hp' => '08'.fake()->numerify('##########'),
            'status' => 'aktif',
            'import_batch_id' => null,
        ];
    }
}
