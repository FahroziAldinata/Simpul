<?php

namespace Database\Factories;

use App\Models\Sekolah;
use App\Models\Semester;
use App\Models\TahunAjaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Semester>
 */
class SemesterFactory extends Factory
{
    protected $model = Semester::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sekolah_id' => Sekolah::factory(),
            'tahun_ajaran_id' => TahunAjaran::factory(),
            'nama' => 'Ganjil',
            'tanggal_mulai' => '2026-07-15',
            'tanggal_selesai' => '2026-12-20',
            'is_aktif' => false,
        ];
    }
}
