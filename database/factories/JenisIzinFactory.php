<?php

namespace Database\Factories;

use App\Models\JenisIzin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JenisIzin>
 */
class JenisIzinFactory extends Factory
{
    protected $model = JenisIzin::class;

    public function definition(): array
    {
        return [
            'sekolah_id'            => null,
            'nama'                  => $this->faker->unique()->words(2, true),
            'kode'                  => $this->faker->unique()->slug(1),
            'butuh_lampiran'        => false,
            'butuh_persetujuan'     => true,
            'mengurangi_kuota_cuti' => false,
            'urutan_approval'       => ['kepsek'],
            'is_aktif'              => true,
        ];
    }

    public function cuti(): static
    {
        return $this->state([
            'nama'                  => 'Cuti Tahunan',
            'kode'                  => 'cuti',
            'mengurangi_kuota_cuti' => true,
            'urutan_approval'       => ['kepsek'],
        ]);
    }

    public function sakit(): static
    {
        return $this->state([
            'nama'             => 'Sakit',
            'kode'             => 'sakit',
            'butuh_lampiran'   => true,
            'urutan_approval'  => [],
            'butuh_persetujuan' => false,
        ]);
    }

    public function tanpaPersetujuan(): static
    {
        return $this->state([
            'butuh_persetujuan' => false,
            'urutan_approval'   => [],
        ]);
    }
}
