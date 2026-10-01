<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSekolah;
use App\Models\Concerns\LogsSimpulActivity;
use Database\Factories\TitikAbsenFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Model TitikAbsen.
 *
 * Mewakili titik check-in absensi QR di sekolah (gerbang, lobi, dll).
 * Setiap titik punya `secret` terenkripsi sendiri — kompromi satu titik
 * tidak membuka titik lain (Keputusan Minggu 8 #3).
 *
 * @property string $id
 * @property string $sekolah_id
 * @property string $nama
 * @property string $secret (plaintext di model, ciphertext di DB)
 * @property float|null $latitude
 * @property float|null $longitude
 * @property bool $is_aktif
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TitikAbsen extends Model
{
    /** @use HasFactory<TitikAbsenFactory> */
    use BelongsToSekolah, HasFactory, HasUuids, LogsSimpulActivity;

    protected $table = 'titik_absen';

    protected $fillable = [
        'sekolah_id',
        'nama',
        'secret',
        'latitude',
        'longitude',
        'is_aktif',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // 'encrypted' cast: plaintext di model, ciphertext di kolom DB
            // Membuktikan enkripsi: SELECT secret FROM titik_absen di psql harus menampilkan ciphertext
            'secret' => 'encrypted',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_aktif' => 'boolean',
        ];
    }

    /** @return BelongsTo<Sekolah, $this> */
    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }
}
