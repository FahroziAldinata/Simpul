<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSekolah;
use App\Models\Concerns\LogsSimpulActivity;
use Database\Factories\PegawaiFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $sekolah_id
 * @property int|null $user_id
 * @property string|null $nuptk
 * @property string|null $nip
 * @property string $nama
 * @property string|null $jenis_kelamin
 * @property string|null $tempat_lahir
 * @property Carbon|null $tanggal_lahir
 * @property string|null $agama
 * @property string|null $alamat
 * @property string|null $no_hp
 * @property string|null $email
 * @property string $jenis
 * @property string $status_kepegawaian
 * @property int $jam_maks_per_minggu
 * @property array<string>|null $hari_tidak_mengajar
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Pegawai extends Model
{
    /** @use HasFactory<PegawaiFactory> */
    use BelongsToSekolah, HasFactory, HasUuids, LogsSimpulActivity, SoftDeletes;

    protected $table = 'pegawai';

    protected $fillable = [
        'sekolah_id',
        'user_id',
        'nuptk',
        'nip',
        'nama',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'agama',
        'alamat',
        'no_hp',
        'email',
        'jenis',
        'status_kepegawaian',
        'jam_maks_per_minggu',
        'hari_tidak_mengajar',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'jam_maks_per_minggu' => 'integer',
            'hari_tidak_mengajar' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (Pegawai $pegawai) {
            if ($pegawai->user_id) {
                $user = User::find($pegawai->user_id);
                if ($user && ! $user->trashed()) {
                    $user->delete();
                }
            }
        });

        static::restoring(function (Pegawai $pegawai) {
            if ($pegawai->user_id) {
                User::withTrashed()->find($pegawai->user_id)?->restore();
            }
        });
    }

    /**
     * @return BelongsTo<Sekolah, $this>
     */
    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return HasMany<AlokasiJamMapel, $this>
     */
    public function alokasiJamMapel(): HasMany
    {
        return $this->hasMany(AlokasiJamMapel::class, 'guru_id');
    }

    /**
     * @return HasMany<Rombel, $this>
     */
    public function rombelWaliKelas(): HasMany
    {
        return $this->hasMany(Rombel::class, 'wali_kelas_id');
    }
}
