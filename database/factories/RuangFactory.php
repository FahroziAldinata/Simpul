<?php

namespace Database\Factories;

use App\Models\Ruang;
use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ruang>
 */
class RuangFactory extends Factory
{
    protected $model = Ruang::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $kategori = fake()->randomElement(['kelas', 'laboratorium', 'bengkel', 'lapangan', 'perpustakaan', 'lainnya']);
        $num = fake()->unique()->numberBetween(100, 999);

        return [
            'sekolah_id' => Sekolah::factory(),
            'kode' => 'R.'.$num,
            'nama' => 'Ruang '.ucfirst($kategori).' '.$num,
            'kategori' => $kategori,
            'kapasitas' => fake()->numberBetween(25, 45),
            'lokasi' => 'Gedung '.fake()->randomElement(['A', 'B', 'C']).' Lt. '.fake()->numberBetween(1, 3),
            'is_aktif' => true,
        ];
    }
}
