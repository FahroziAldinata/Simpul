<?php

namespace Database\Factories;

use App\Models\Rombel;
use App\Models\Sekolah;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rombel>
 */
class RombelFactory extends Factory
{
    protected $model = Rombel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tingkat = fake()->randomElement([7, 8, 9, 10, 11, 12]);
        $suffix = fake()->unique()->lexify('?');

        return [
            'sekolah_id' => Sekolah::factory(),
            'semester_id' => Semester::factory(),
            'jurusan_id' => null,
            'wali_kelas_id' => null,
            'ruang_id' => null,
            'nama' => "Kelas {$tingkat}-".strtoupper($suffix),
            'tingkat' => $tingkat,
            'kuota' => 36,
            'is_aktif' => true,
        ];
    }
}
