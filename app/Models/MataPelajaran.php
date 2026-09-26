<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSekolah;
use App\Models\Concerns\LogsSimpulActivity;
use Database\Factories\MataPelajaranFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $sekolah_id
 * @property string $kode
 * @property string $nama
 * @property string $kelompok
 * @property int|null $tingkat
 * @property string|null $jurusan_id
 * @property string $bobot_beban_kognitif
 * @property string|null $butuh_ruang_kategori
 * @property bool $is_aktif
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MataPelajaran extends Model
{
    /** @use HasFactory<MataPelajaranFactory> */
    use BelongsToSekolah, HasFactory, HasUuids, LogsSimpulActivity;

    protected $table = 'mata_pelajaran';

    protected $fillable = [
        'sekolah_id',
        'kode',
        'nama',
        'kelompok',
        'tingkat',
        'jurusan_id',
        'bobot_beban_kognitif',
        'butuh_ruang_kategori',
        'is_aktif',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tingkat' => 'integer',
            'is_aktif' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Jurusan, $this>
     */
    public function jurusan(): BelongsTo
    {
        return $this->belongsTo(Jurusan::class, 'jurusan_id');
    }
}
