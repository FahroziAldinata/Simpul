<?php

namespace App\Models;

use App\Enums\StatusPengajuanIzin;
use App\Models\Concerns\BelongsToSekolah;
use App\Models\Concerns\LogsSimpulActivity;
use Database\Factories\PengajuanIzinFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Model PengajuanIzin — pengajuan izin/cuti/sakit/dinas oleh pegawai (T-09.02).
 *
 * @property string $id
 * @property string $sekolah_id
 * @property string $pegawai_id
 * @property string $jenis_izin_id
 * @property Carbon $tanggal_mulai
 * @property Carbon $tanggal_selesai
 * @property string $alasan
 * @property string|null $lampiran_path
 * @property string|null $lampiran_mime
 * @property StatusPengajuanIzin $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PengajuanIzin extends Model
{
    /** @use HasFactory<PengajuanIzinFactory> */
    use BelongsToSekolah, HasFactory, HasUuids, LogsSimpulActivity;

    protected $table = 'pengajuan_izin';

    protected $fillable = [
        'sekolah_id',
        'pegawai_id',
        'jenis_izin_id',
        'tanggal_mulai',
        'tanggal_selesai',
        'alasan',
        'lampiran_path',
        'lampiran_mime',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'status' => StatusPengajuanIzin::class,
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

    /** @return BelongsTo<JenisIzin, $this> */
    public function jenisIzin(): BelongsTo
    {
        return $this->belongsTo(JenisIzin::class);
    }

    /** @return HasMany<PersetujuanIzin, $this> */
    public function persetujuan(): HasMany
    {
        return $this->hasMany(PersetujuanIzin::class)->orderBy('urutan');
    }

    /**
     * Langkah persetujuan aktif saat ini (urutan terendah yang masih menunggu).
     *
     * @return HasMany<PersetujuanIzin, $this>
     */
    public function persetujuanAktif(): HasMany
    {
        return $this->hasMany(PersetujuanIzin::class)
            ->where('status', 'menunggu')
            ->orderBy('urutan')
            ->limit(1);
    }

    /**
     * Jumlah hari kerja yang dicakup pengajuan ini.
     * Sederhana: selisih kalender (inklusif kedua ujung).
     */
    public function jumlahHari(): int
    {
        return (int) $this->tanggal_mulai->diffInDays($this->tanggal_selesai) + 1;
    }
}
