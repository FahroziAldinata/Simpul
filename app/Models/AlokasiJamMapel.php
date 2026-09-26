<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSekolah;
use App\Models\Concerns\LogsSimpulActivity;
use Database\Factories\AlokasiJamMapelFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $sekolah_id
 * @property string $rombel_id
 * @property string $mata_pelajaran_id
 * @property string|null $guru_id
 * @property int $jam_per_minggu
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AlokasiJamMapel extends Model
{
    /** @use HasFactory<AlokasiJamMapelFactory> */
    use BelongsToSekolah, HasFactory, HasUuids, LogsSimpulActivity;

    protected $table = 'alokasi_jam_mapel';

    protected $fillable = [
        'sekolah_id',
        'rombel_id',
        'mata_pelajaran_id',
        'guru_id',
        'jam_per_minggu',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jam_per_minggu' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Rombel, $this>
     */
    public function rombel(): BelongsTo
    {
        return $this->belongsTo(Rombel::class);
    }

    /**
     * @return BelongsTo<MataPelajaran, $this>
     */
    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    /**
     * @return BelongsTo<Pegawai, $this>
     */
    public function guru(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'guru_id');
    }
}
