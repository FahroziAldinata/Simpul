<?php

namespace Database\Factories;

use App\Models\Sekolah;
use App\Models\TahunAjaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TahunAjaran>
 */
class TahunAjaranFactory extends Factory
{
    protected $model = TahunAjaran::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = fake()->numberBetween(2024, 2030);

        return [
            'sekolah_id' => Sekolah::factory(),
            'nama' => "{$year}/".($year + 1),
            'tanggal_mulai' => "{$year}-07-15",
            'tanggal_selesai' => ($year + 1).'-06-20',
            'is_aktif' => false,
        ];
    }
}
