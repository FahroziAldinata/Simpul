<?php

namespace Database\Factories;

use App\Enums\JenisBerkasSiswa;
use App\Models\BerkasSiswa;
use App\Models\Sekolah;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BerkasSiswa>
 */
class BerkasSiswaFactory extends Factory
{
    protected $model = BerkasSiswa::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sekolah_id' => Sekolah::factory(),
            'siswa_id' => Siswa::factory(),
            'jenis' => fake()->randomElement(JenisBerkasSiswa::cases()),
            'file_path' => 'berkas/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => fake()->numberBetween(50000, 5000000),
        ];
    }
}
