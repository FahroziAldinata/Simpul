<?php

namespace App\Models;

use App\Enums\JenisBerkasSiswa;
use App\Models\Concerns\BelongsToSekolah;
use Database\Factories\BerkasSiswaFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/**
 * @property string $id
 * @property string $sekolah_id
 * @property string $siswa_id
 * @property JenisBerkasSiswa $jenis
 * @property string $file_path
 * @property string $mime_type
 * @property int $file_size_bytes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
use Illuminate\Support\Carbon;

class BerkasSiswa extends Model
{
    /** @use HasFactory<BerkasSiswaFactory> */
    use BelongsToSekolah, HasFactory, HasUuids;

    protected $table = 'berkas_siswa';

    protected $fillable = [
        'sekolah_id',
        'siswa_id',
        'jenis',
        'file_path',
        'mime_type',
        'file_size_bytes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jenis' => JenisBerkasSiswa::class,
            'file_size_bytes' => 'integer',
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
}
