<?php

namespace Database\Factories;

use App\Enums\JenisMutasi;
use App\Models\MutasiSiswa;
use App\Models\Sekolah;
use App\Models\Semester;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MutasiSiswa>
 */
class MutasiSiswaFactory extends Factory
{
    protected $model = MutasiSiswa::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sekolah_id' => Sekolah::factory(),
            'siswa_id' => Siswa::factory(),
            'semester_id' => Semester::factory(),
            'tipe' => fake()->randomElement(JenisMutasi::cases()),
            'tanggal' => fake()->date(),
            'alasan' => fake()->sentence(),
            'asal_sekolah' => null,
            'sekolah_tujuan' => null,
            'dari_rombel_id' => null,
            'ke_rombel_id' => null,
            'status_sebelum' => 'aktif',
            'rombel_id_sebelum' => null,
            'anggota_rombel_dibuat_baru' => false,
            'is_batal' => false,
            'alasan_batal' => null,
            'dibatalkan_oleh' => null,
            'dibatalkan_at' => null,
        ];
    }
}
