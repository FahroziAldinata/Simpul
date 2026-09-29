<?php

namespace App\Models;

use App\Enums\JenisMutasi;
use App\Models\Concerns\BelongsToSekolah;
use App\Models\Concerns\LogsSimpulActivity;
use Database\Factories\MutasiSiswaFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $sekolah_id
 * @property string $siswa_id
 * @property string $semester_id
 * @property JenisMutasi $tipe
 * @property Carbon $tanggal
 * @property string|null $alasan
 * @property string|null $asal_sekolah
 * @property string|null $sekolah_tujuan
 * @property string|null $dari_rombel_id
 * @property string|null $ke_rombel_id
 * @property string|null $status_sebelum
 * @property string|null $rombel_id_sebelum
 * @property bool $anggota_rombel_dibuat_baru
 * @property bool $is_batal
 * @property string|null $alasan_batal
 * @property int|null $dibatalkan_oleh
 * @property Carbon|null $dibatalkan_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MutasiSiswa extends Model
{
    /** @use HasFactory<MutasiSiswaFactory> */
    use BelongsToSekolah, HasFactory, HasUuids, LogsSimpulActivity;

    protected $table = 'mutasi_siswa';

    protected $fillable = [
        'sekolah_id',
        'siswa_id',
        'semester_id',
        'tipe',
        'tanggal',
        'alasan',
        'asal_sekolah',
        'sekolah_tujuan',
        'dari_rombel_id',
        'ke_rombel_id',
        'status_sebelum',
        'rombel_id_sebelum',
        'anggota_rombel_dibuat_baru',
        'is_batal',
        'alasan_batal',
        'dibatalkan_oleh',
        'dibatalkan_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipe' => JenisMutasi::class,
            'tanggal' => 'date',
            'anggota_rombel_dibuat_baru' => 'boolean',
            'is_batal' => 'boolean',
            'dibatalkan_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Sekolah, $this>
     */
    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }

    /**
     * @return BelongsTo<Siswa, $this>
     */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    /**
     * @return BelongsTo<Semester, $this>
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * @return BelongsTo<Rombel, $this>
     */
    public function dariRombel(): BelongsTo
    {
        return $this->belongsTo(Rombel::class, 'dari_rombel_id');
    }

    /**
     * @return BelongsTo<Rombel, $this>
     */
    public function keRombel(): BelongsTo
    {
        return $this->belongsTo(Rombel::class, 'ke_rombel_id');
    }

    /**
     * @return BelongsTo<Rombel, $this>
     */
    public function rombelSebelum(): BelongsTo
    {
        return $this->belongsTo(Rombel::class, 'rombel_id_sebelum');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function dibatalkanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibatalkan_oleh');
    }
}
