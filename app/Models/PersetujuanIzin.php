<?php

namespace App\Models;

use App\Enums\StatusPersetujuanIzin;
use App\Models\Concerns\LogsSimpulActivity;
use Database\Factories\PersetujuanIzinFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Model PersetujuanIzin — satu langkah dalam alur persetujuan berjenjang (T-09.03).
 *
 * @property string $id
 * @property string $pengajuan_izin_id
 * @property int $urutan              Urutan langkah (1, 2, 3...)
 * @property string $approver_role    Role slug yang berhak di langkah ini
 * @property int|null $approver_id   User yang memutuskan (null = belum)
 * @property StatusPersetujuanIzin $status
 * @property string|null $catatan
 * @property Carbon|null $diputuskan_pada
 */
class PersetujuanIzin extends Model
{
    /** @use HasFactory<PersetujuanIzinFactory> */
    use HasFactory, HasUuids, LogsSimpulActivity;

    protected $table = 'persetujuan_izin';

    protected $fillable = [
        'pengajuan_izin_id',
        'urutan',
        'approver_role',
        'approver_id',
        'status',
        'catatan',
        'diputuskan_pada',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'urutan'          => 'integer',
            'status'          => StatusPersetujuanIzin::class,
            'diputuskan_pada' => 'datetime',
        ];
    }

    /** @return BelongsTo<PengajuanIzin, $this> */
    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(PengajuanIzin::class, 'pengajuan_izin_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
