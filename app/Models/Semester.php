<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSekolah;
use App\Models\Concerns\LogsSimpulActivity;
use Database\Factories\SemesterFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $sekolah_id
 * @property string $tahun_ajaran_id
 * @property string $nama
 * @property Carbon $tanggal_mulai
 * @property Carbon $tanggal_selesai
 * @property bool $is_aktif
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Semester extends Model
{
    /** @use HasFactory<SemesterFactory> */
    use BelongsToSekolah, HasFactory, HasUuids, LogsSimpulActivity;

    protected $table = 'semester';

    protected $fillable = [
        'sekolah_id',
        'tahun_ajaran_id',
        'nama',
        'tanggal_mulai',
        'tanggal_selesai',
        'is_aktif',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'is_aktif' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<TahunAjaran, $this>
     */
    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id');
    }
}
