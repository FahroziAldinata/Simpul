<?php

namespace App\Models;

use Database\Factories\SekolahFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $npsn
 * @property string $nama
 * @property string $jenjang
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Sekolah extends Model
{
    /** @use HasFactory<SekolahFactory> */
    use \App\Models\Concerns\LogsSimpulActivity, HasFactory, HasUuids, SoftDeletes;

    protected $table = 'sekolah';

    protected $fillable = [
        'npsn',
        'nama',
        'jenjang',
        'status',
    ];

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
