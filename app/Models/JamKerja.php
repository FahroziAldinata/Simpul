<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSekolah;
use App\Models\Concerns\LogsSimpulActivity;
use Database\Factories\JamKerjaFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $sekolah_id
 * @property string $kelompok
 * @property int $hari
 * @property string|null $jam_masuk
 * @property string|null $jam_pulang
 * @property bool $is_libur
 * @property int $jumlah_jam_pelajaran
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class JamKerja extends Model
{
    /** @use HasFactory<JamKerjaFactory> */
    use BelongsToSekolah, HasFactory, HasUuids, LogsSimpulActivity;

    protected $table = 'jam_kerja';

    protected $fillable = [
        'sekolah_id',
        'kelompok',
        'hari',
        'jam_masuk',
        'jam_pulang',
        'is_libur',
        'jumlah_jam_pelajaran',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hari' => 'integer',
            'is_libur' => 'boolean',
            'jumlah_jam_pelajaran' => 'integer',
        ];
    }

    /**
     * Hitung total slot jam pelajaran operasional yang tersedia per minggu.
     * Fallback 40 jam jika belum dikonfigurasi (kesepakatan sementara Minggu 4).
     */
    public static function totalSlotMingguan(?string $sekolahId = null): int
    {
        $targetSekolahId = $sekolahId ?? session('sekolah_id');

        if (! $targetSekolahId) {
            return 40; // Fallback default
        }

        $query = static::where('kelompok', 'umum')
            ->where('is_libur', false);

        if ($query->count() === 0) {
            return 40; // TODO: Konfigurasi default sebelum jam kerja diatur sekolah
        }

        return (int) $query->sum('jumlah_jam_pelajaran');
    }
}
