<?php

namespace App\Models;

use App\Enums\JenisKelamin;
use App\Enums\StatusSiswa;
use App\Models\Concerns\BelongsToSekolah;
use App\Models\Concerns\LogsSimpulActivity;
use Database\Factories\SiswaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $sekolah_id
 * @property string $nisn
 * @property string $nik
 * @property string $nama
 * @property JenisKelamin $jenis_kelamin
 * @property string $tempat_lahir
 * @property Carbon $tanggal_lahir
 * @property string $agama
 * @property string|null $alamat
 * @property string|null $no_hp
 * @property StatusSiswa $status
 * @property string|null $import_batch_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Siswa extends Model
{
    /** @use HasFactory<SiswaFactory> */
    use BelongsToSekolah, HasFactory, HasUuids, LogsSimpulActivity, SoftDeletes;

    protected $table = 'siswa';

    protected $fillable = [
        'sekolah_id',
        'nisn',
        'nik',
        'nama',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'agama',
        'alamat',
        'no_hp',
        'status',
        'import_batch_id',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'is_data_lengkap',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jenis_kelamin' => JenisKelamin::class,
            'status' => StatusSiswa::class,
            'tanggal_lahir' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::restoring(function (Siswa $siswa) {
            $conflict = static::withoutGlobalScopes()
                ->where('nisn', $siswa->nisn)
                ->whereNull('deleted_at')
                ->where('id', '!=', $siswa->id)
                ->exists();

            if ($conflict) {
                throw new \RuntimeException("Gagal memulihkan data siswa: NISN {$siswa->nisn} sudah aktif digunakan oleh siswa lain.");
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
     * @return HasMany<WaliSiswa, $this>
     */
    public function wali(): HasMany
    {
        return $this->hasMany(WaliSiswa::class, 'siswa_id');
    }

    /**
     * @return HasMany<AnggotaRombel, $this>
     */
    public function anggotaRombel(): HasMany
    {
        return $this->hasMany(AnggotaRombel::class, 'siswa_id');
    }

    /**
     * Get the active rombel membership for the current active semester.
     *
     * @return HasOne<AnggotaRombel, $this>
     */
    public function anggotaRombelAktif(): HasOne
    {
        return $this->hasOne(AnggotaRombel::class, 'siswa_id')
            ->whereHas('semester', function (Builder $query) {
                $query->where('is_aktif', true);
            });
    }

    /**
     * @return HasMany<BerkasSiswa, $this>
     */
    public function berkas(): HasMany
    {
        return $this->hasMany(BerkasSiswa::class, 'siswa_id');
    }

    /**
     * Compute data completeness indicator without triggering lazy loading.
     */
    public function getIsDataLengkapAttribute(): bool
    {
        $hasBasic = ! empty($this->nisn) && strlen((string) $this->nisn) === 10
            && ! empty($this->nik) && strlen((string) $this->nik) === 16
            && ! empty($this->tempat_lahir)
            && ! empty($this->tanggal_lahir)
            && ! empty($this->alamat)
            && ! empty($this->no_hp);

        if (! $hasBasic) {
            return false;
        }

        // Only check wali if already eager loaded to completely prevent lazy loading
        if ($this->relationLoaded('wali')) {
            return $this->wali->contains(function (WaliSiswa $w) {
                return ! empty($w->nama) && (! empty($w->no_hp) || ! empty($w->pekerjaan));
            });
        }

        return false;
    }

    /**
     * Scope to filter students with complete data (SQL scope, no N+1).
     *
     * @param  Builder<Siswa>  $query
     * @return Builder<Siswa>
     */
    public function scopeDataLengkap(Builder $query): Builder
    {
        return $query
            ->whereNotNull('nisn')
            ->whereRaw('length(nisn) = 10')
            ->whereNotNull('nik')
            ->whereRaw('length(nik) = 16')
            ->whereNotNull('tempat_lahir')
            ->whereNotNull('tanggal_lahir')
            ->whereNotNull('alamat')
            ->where('alamat', '!=', '')
            ->whereNotNull('no_hp')
            ->where('no_hp', '!=', '')
            ->whereHas('wali', function (Builder $w) {
                $w->whereNotNull('nama')
                    ->where('nama', '!=', '')
                    ->where(function (Builder $sub) {
                        $sub->whereNotNull('no_hp')
                            ->orWhereNotNull('pekerjaan');
                    });
            });
    }

    /**
     * Scope to filter students with incomplete data (SQL scope, no N+1).
     *
     * @param  Builder<Siswa>  $query
     * @return Builder<Siswa>
     */
    public function scopeDataBelumLengkap(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('nisn')
                ->orWhereRaw('length(nisn) != 10')
                ->orWhereNull('nik')
                ->orWhereRaw('length(nik) != 16')
                ->orWhereNull('tempat_lahir')
                ->orWhereNull('tanggal_lahir')
                ->orWhereNull('alamat')
                ->orWhere('alamat', '=', '')
                ->orWhereNull('no_hp')
                ->orWhere('no_hp', '=', '')
                ->orWhereDoesntHave('wali', function (Builder $w) {
                    $w->whereNotNull('nama')
                        ->where('nama', '!=', '')
                        ->where(function (Builder $sub) {
                            $sub->whereNotNull('no_hp')
                                ->orWhereNotNull('pekerjaan');
                        });
                });
        });
    }
}
