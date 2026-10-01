<?php

namespace Database\Factories;

use App\Enums\StatusPersetujuanIzin;
use App\Models\PengajuanIzin;
use App\Models\PersetujuanIzin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersetujuanIzin>
 */
class PersetujuanIzinFactory extends Factory
{
    protected $model = PersetujuanIzin::class;

    public function definition(): array
    {
        return [
            'pengajuan_izin_id' => PengajuanIzin::factory(),
            'urutan' => 1,
            'approver_role' => 'kepsek',
            'approver_id' => null,
            'status' => StatusPersetujuanIzin::Menunggu,
            'catatan' => null,
            'diputuskan_pada' => null,
        ];
    }

    public function disetujui(): static
    {
        return $this->state([
            'status' => StatusPersetujuanIzin::Disetujui,
            'diputuskan_pada' => now(),
        ]);
    }

    public function ditolak(): static
    {
        return $this->state([
            'status' => StatusPersetujuanIzin::Ditolak,
            'catatan' => 'Ditolak oleh approver.',
            'diputuskan_pada' => now(),
        ]);
    }
}
