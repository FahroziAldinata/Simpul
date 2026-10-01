<?php

namespace Database\Factories;

use App\Enums\JenisAbsensi;
use App\Enums\StatusAbsensi;
use App\Models\Absensi;
use App\Models\Pegawai;
use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Absensi>
 */
class AbsensiFactory extends Factory
{
    protected $model = Absensi::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sekolah_id' => Sekolah::factory(),
            'pegawai_id' => Pegawai::factory(),
            'tanggal' => fake()->date(),
            'jenis' => JenisAbsensi::Masuk,
            'waktu_server' => now(),
            'waktu_perangkat' => now(),
            'status' => StatusAbsensi::Hadir,
            'menit_terlambat' => 0,
            'latitude' => fake()->latitude(-6.3, -6.1),
            'longitude' => fake()->longitude(106.7, 106.9),
            'lokasi_mencurigakan' => false,
            'perlu_ditinjau' => false,
            'sumber' => 'qr',
            'client_uuid' => fake()->uuid(),
            'dicatat_oleh' => null,
            'alasan_manual' => null,
        ];
    }
}
