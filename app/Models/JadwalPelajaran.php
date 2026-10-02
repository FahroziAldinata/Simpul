<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSekolah;
use App\Models\Concerns\LogsSimpulActivity;
use Database\Factories\JadwalPelajaranFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $sekolah_id
 * @property string $semester_id
 * @property string $rombel_id
 * @property string $mata_pelajaran_id
 * @property string $guru_id
 * @property string|null $ruang_id
 * @property int $hari 1=Senin..6=Sabtu
 * @property int $jam_mulai_ke
 * @property int $jam_selesai_ke
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read int $durasi_jam
 * @property-read Sekolah $sekolah
 * @property-read Semester $semester
 * @property-read Rombel $rombel
 * @property-read MataPelajaran $mataPelajaran
 * @property-read Pegawai $guru
 * @property-read Ruang|null $ruang
 */
class JadwalPelajaran extends Model
{
    /** @use HasFactory<JadwalPelajaranFactory> */
    use BelongsToSekolah, HasFactory, HasUuids, LogsSimpulActivity, SoftDeletes;

    protected $table = 'jadwal_pelajaran';

    protected $fillable = [
        'sekolah_id',
        'semester_id',
        'rombel_id',
        'mata_pelajaran_id',
        'guru_id',
        'ruang_id',
        'hari',
        'jam_mulai_ke',
        'jam_selesai_ke',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hari' => 'integer',
            'jam_mulai_ke' => 'integer',
            'jam_selesai_ke' => 'integer',
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
     * @return BelongsTo<Semester, $this>
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
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

    /**
     * @return BelongsTo<Ruang, $this>
     */
    public function ruang(): BelongsTo
    {
        return $this->belongsTo(Ruang::class);
    }

    /**
     * Durasi jam pelajaran (jam_selesai_ke - jam_mulai_ke).
     *
     * @return Attribute<int, never>
     */
    protected function durasiJam(): Attribute
    {
        return Attribute::make(
            get: fn (): int => max(0, $this->jam_selesai_ke - $this->jam_mulai_ke),
        );
    }
}
