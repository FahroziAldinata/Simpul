<?php

namespace App\Models;

use App\Models\Concerns\LogsSimpulActivity;
use Database\Factories\SekolahFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $id
 * @property string $npsn
 * @property string $nama
 * @property string $jenjang
 * @property string $status
 * @property string|null $alamat
 * @property float|null $latitude
 * @property float|null $longitude
 * @property int $radius_absen_meter
 * @property string|null $logo_path
 * @property string|null $logo_url
 * @property string|null $kepala_sekolah
 * @property string|null $akreditasi
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Sekolah extends Model
{
    /** @use HasFactory<SekolahFactory> */
    use HasFactory, HasUuids, LogsSimpulActivity, SoftDeletes;

    protected $table = 'sekolah';

    protected $fillable = [
        'npsn',
        'nama',
        'jenjang',
        'status',
        'alamat',
        'latitude',
        'longitude',
        'radius_absen_meter',
        'logo_path',
        'kepala_sekolah',
        'akreditasi',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'logo_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_absen_meter' => 'integer',
        ];
    }

    /**
     * Get signed temporary URL for school logo in MinIO.
     */
    public function getLogoUrlAttribute(): ?string
    {
        $logoPath = $this->attributes['logo_path'] ?? null;

        if (! $logoPath) {
            return null;
        }

        try {
            return Storage::disk('s3')->temporaryUrl($logoPath, now()->addMinutes(60));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return HasMany<Pegawai, $this>
     */
    public function pegawai(): HasMany
    {
        return $this->hasMany(Pegawai::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
