<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSekolah;
use App\Models\Concerns\LogsSimpulActivity;
use Database\Factories\AnggotaRombelFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $sekolah_id
 * @property string $rombel_id
 * @property string $siswa_id
 * @property string $semester_id
 * @property int|null $nomor_absen
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AnggotaRombel extends Model
{
    /** @use HasFactory<AnggotaRombelFactory> */
    use BelongsToSekolah, HasFactory, HasUuids, LogsSimpulActivity;

    protected $table = 'anggota_rombel';

    protected $fillable = [
        'sekolah_id',
        'rombel_id',
        'siswa_id',
        'semester_id',
        'nomor_absen',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nomor_absen' => 'integer',
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
     * @return BelongsTo<Rombel, $this>
     */
    public function rombel(): BelongsTo
    {
        return $this->belongsTo(Rombel::class);
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
}
