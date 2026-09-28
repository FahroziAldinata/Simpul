<?php

namespace App\Models;

use App\Enums\HubunganWali;
use App\Models\Concerns\BelongsToSekolah;
use App\Models\Concerns\LogsSimpulActivity;
use Database\Factories\WaliSiswaFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $sekolah_id
 * @property string $siswa_id
 * @property HubunganWali $hubungan
 * @property string $nama
 * @property string|null $pekerjaan
 * @property string|null $no_hp
 * @property string|null $alamat
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class WaliSiswa extends Model
{
    /** @use HasFactory<WaliSiswaFactory> */
    use BelongsToSekolah, HasFactory, HasUuids, LogsSimpulActivity;

    protected $table = 'wali_siswa';

    protected $fillable = [
        'sekolah_id',
        'siswa_id',
        'hubungan',
        'nama',
        'pekerjaan',
        'no_hp',
        'alamat',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hubungan' => HubunganWali::class,
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
