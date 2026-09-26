<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSekolah;
use App\Models\Concerns\LogsSimpulActivity;
use Database\Factories\RombelFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $sekolah_id
 * @property string $semester_id
 * @property string|null $jurusan_id
 * @property string|null $wali_kelas_id
 * @property string|null $ruang_id
 * @property string $nama
 * @property int $tingkat
 * @property int $kuota
 * @property bool $is_aktif
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Rombel extends Model
{
    /** @use HasFactory<RombelFactory> */
    use BelongsToSekolah, HasFactory, HasUuids, LogsSimpulActivity;

    protected $table = 'rombel';

    protected $fillable = [
        'sekolah_id',
        'semester_id',
        'jurusan_id',
        'wali_kelas_id',
        'ruang_id',
        'nama',
        'tingkat',
        'kuota',
        'is_aktif',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tingkat' => 'integer',
            'kuota' => 'integer',
            'is_aktif' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Semester, $this>
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * @return BelongsTo<Jurusan, $this>
     */
    public function jurusan(): BelongsTo
    {
        return $this->belongsTo(Jurusan::class);
    }

    /**
     * @return BelongsTo<Pegawai, $this>
     */
    public function waliKelas(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'wali_kelas_id');
    }

    /**
     * @return BelongsTo<Ruang, $this>
     */
    public function ruang(): BelongsTo
    {
        return $this->belongsTo(Ruang::class);
    }
}
