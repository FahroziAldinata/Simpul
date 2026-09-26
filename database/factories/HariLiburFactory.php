<?php

namespace Database\Factories;

use App\Models\HariLibur;
use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HariLibur>
 */
class HariLiburFactory extends Factory
{
    protected $model = HariLibur::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+6 months');

        return [
            'sekolah_id' => Sekolah::factory(),
            'tanggal_mulai' => $start->format('Y-m-d'),
            'tanggal_selesai' => $start->format('Y-m-d'),
            'keterangan' => fake()->sentence(3),
            'jenis' => fake()->randomElement(['nasional', 'sekolah', 'ujian', 'kegiatan']),
        ];
    }
}
