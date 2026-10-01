<?php

namespace Database\Factories;

use App\Models\KuotaCuti;
use App\Models\Pegawai;
use App\Models\Sekolah;
use App\Models\TahunAjaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KuotaCuti>
 */
class KuotaCutiFactory extends Factory
{
    protected $model = KuotaCuti::class;

    public function definition(): array
    {
        return [
            'sekolah_id' => Sekolah::factory(),
            'pegawai_id' => Pegawai::factory(),
            'tahun_ajaran_id' => TahunAjaran::factory(),
            'kuota_hari' => 12,
            'terpakai' => 0,
        ];
    }
}
