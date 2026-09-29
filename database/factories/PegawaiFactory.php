<?php

namespace Database\Factories;

use App\Models\Pegawai;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pegawai>
 */
class PegawaiFactory extends Factory
{
    protected $model = Pegawai::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sekolah_id' => Sekolah::factory(),
            'user_id' => User::factory(),
            'nuptk' => fake()->numerify('################'),
            'nip' => fake()->numerify('##################'),
            'nama' => fake()->name(),
            'jenis_kelamin' => fake()->randomElement(['L', 'P']),
            'tempat_lahir' => fake()->city(),
            'tanggal_lahir' => fake()->date('Y-m-d', '-25 years'),
            'agama' => fake()->randomElement(['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha']),
            'alamat' => fake()->address(),
            'no_hp' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'jenis' => fake()->randomElement(['guru', 'tu', 'kepsek']),
            'status_kepegawaian' => fake()->randomElement(['pns', 'pppk', 'gty', 'gtt']),
            'jam_maks_per_minggu' => 24,
            'hari_tidak_mengajar' => [],
        ];
    }
}
