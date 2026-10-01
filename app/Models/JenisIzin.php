<?php

namespace App\Models;

use App\Models\Concerns\LogsSimpulActivity;
use Database\Factories\JenisIzinFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model JenisIzin — konfigurasi jenis izin/cuti/sakit/dinas per sekolah (T-09.01).
 *
 * @property string $id
 * @property string|null $sekolah_id  null = default global (seed)
 * @property string $nama            "Cuti Tahunan", "Sakit", "Dinas Luar", "Izin"
 * @property string|null $kode       "cuti", "sakit", "dinas", "izin"
 * @property bool $butuh_lampiran
 * @property bool $butuh_persetujuan
 * @property bool $mengurangi_kuota_cuti
 * @property array<int, string> $urutan_approval  Array of role slugs ["waka_kurikulum","kepsek"]
 * @property bool $is_aktif
 */
class JenisIzin extends Model
{
    /** @use HasFactory<JenisIzinFactory> */
    use HasFactory, HasUuids, LogsSimpulActivity;

    protected $table = 'jenis_izin';

    protected $fillable = [
        'sekolah_id',
        'nama',
        'kode',
        'butuh_lampiran',
        'butuh_persetujuan',
        'mengurangi_kuota_cuti',
        'urutan_approval',
        'is_aktif',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'butuh_lampiran'        => 'boolean',
            'butuh_persetujuan'     => 'boolean',
            'mengurangi_kuota_cuti' => 'boolean',
            'urutan_approval'       => 'array',
            'is_aktif'              => 'boolean',
        ];
    }

    /** @return BelongsTo<Sekolah, $this> */
    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }

    /** @return HasMany<PengajuanIzin, $this> */
    public function pengajuan(): HasMany
    {
        return $this->hasMany(PengajuanIzin::class);
    }
}
