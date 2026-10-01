<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSekolah;
use App\Models\Concerns\LogsSimpulActivity;
use Database\Factories\KuotaCutiFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model KuotaCuti — kuota cuti tahunan per pegawai per tahun ajaran (T-09.06).
 *
 * Known limitation (ADR-0000): reset otomatis menyusul Rollover Minggu 13.
 * Untuk Minggu 9, baris dibuat saat diperlukan oleh IzinApprovalService
 * atau dapat dikelola Operator.
 *
 * @property string $id
 * @property string $sekolah_id
 * @property string $pegawai_id
 * @property string $tahun_ajaran_id
 * @property int $kuota_hari
 * @property int $terpakai
 */
class KuotaCuti extends Model
{
    /** @use HasFactory<KuotaCutiFactory> */
    use BelongsToSekolah, HasFactory, HasUuids, LogsSimpulActivity;

    protected $table = 'kuota_cuti';

    protected $fillable = [
        'sekolah_id',
        'pegawai_id',
        'tahun_ajaran_id',
        'kuota_hari',
        'terpakai',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kuota_hari' => 'integer',
            'terpakai'   => 'integer',
        ];
    }

    /** @return BelongsTo<Sekolah, $this> */
    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }

    /** @return BelongsTo<Pegawai, $this> */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    /** @return BelongsTo<TahunAjaran, $this> */
    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    /**
     * Sisa kuota yang belum terpakai.
     */
    public function sisaHari(): int
    {
        return max(0, $this->kuota_hari - $this->terpakai);
    }
}
