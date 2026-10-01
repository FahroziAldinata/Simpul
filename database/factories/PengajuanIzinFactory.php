<?php

namespace Database\Factories;

use App\Enums\StatusPengajuanIzin;
use App\Models\JenisIzin;
use App\Models\Pegawai;
use App\Models\PengajuanIzin;
use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<PengajuanIzin>
 */
class PengajuanIzinFactory extends Factory
{
    protected $model = PengajuanIzin::class;

    public function definition(): array
    {
        $mulai = Carbon::parse($this->faker->dateTimeBetween('-30 days', '+7 days'));

        return [
            'sekolah_id' => Sekolah::factory(),
            'pegawai_id' => Pegawai::factory(),
            'jenis_izin_id' => JenisIzin::factory(),
            'tanggal_mulai' => $mulai->toDateString(),
            'tanggal_selesai' => $mulai->copy()->addDays(rand(0, 2))->toDateString(),
            'alasan' => $this->faker->sentence(8),
            'lampiran_path' => null,
            'lampiran_mime' => null,
            'status' => StatusPengajuanIzin::Menunggu,
        ];
    }

    public function disetujui(): static
    {
        return $this->state(['status' => StatusPengajuanIzin::Disetujui]);
    }

    public function ditolak(): static
    {
        return $this->state(['status' => StatusPengajuanIzin::Ditolak]);
    }
}
