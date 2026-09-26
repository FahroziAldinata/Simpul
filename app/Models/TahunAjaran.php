<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSekolah;
use App\Models\Concerns\LogsSimpulActivity;
use Database\Factories\TahunAjaranFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $sekolah_id
 * @property string $nama
 * @property Carbon $tanggal_mulai
 * @property Carbon $tanggal_selesai
 * @property bool $is_aktif
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TahunAjaran extends Model
{
    /** @use HasFactory<TahunAjaranFactory> */
    use BelongsToSekolah, HasFactory, HasUuids, LogsSimpulActivity;

    protected $table = 'tahun_ajaran';

    protected $fillable = [
        'sekolah_id',
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
     * @return HasMany<Semester, $this>
     */
    public function semester(): HasMany
    {
        return $this->hasMany(Semester::class, 'tahun_ajaran_id');
    }
}
