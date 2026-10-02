<?php

namespace Database\Factories;

use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Rombel;
use App\Models\Ruang;
use App\Models\Sekolah;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JadwalPelajaran>
 */
class JadwalPelajaranFactory extends Factory
{
    protected $model = JadwalPelajaran::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $jamMulai = fake()->numberBetween(1, 8);
        $durasi = fake()->numberBetween(1, 2);

        return [
            'sekolah_id' => Sekolah::factory(),
            'semester_id' => Semester::factory(),
            'rombel_id' => Rombel::factory(),
            'mata_pelajaran_id' => MataPelajaran::factory(),
            'guru_id' => Pegawai::factory(),
            'ruang_id' => Ruang::factory(),
            'hari' => fake()->numberBetween(1, 5), // 1=Senin..5=Jumat
            'jam_mulai_ke' => $jamMulai,
            'jam_selesai_ke' => $jamMulai + $durasi,
        ];
    }
}
