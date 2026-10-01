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
 * Model JamKerja.
 *
 * Catatan Arsitektur:
 * Model ini dibuat pada Minggu 4 (T-04.10) untuk jam operasional harian sekolah ($kelompok = 'umum')
 * dan perhitungan total slot alokasi mingguan (totalSlotMingguan).
 * Model ini akan di-reuse & di-extend pada Minggu 8 (T-08.02) untuk jam kerja pegawai / guru
 * (dengan kolom toleransi_menit dan kelompok shift kerja spesifik) tanpa membuat tabel baru.
 *
 * @property string $id
 * @property string $sekolah_id
 * @property string $kelompok
 * @property int $hari
 * @property string|null $jam_masuk
 * @property string|null $jam_pulang
 * @property bool $is_libur
 * @property int $jumlah_jam_pelajaran
 * @property int $toleransi_menit Toleransi keterlambatan dalam menit (ditambahkan Minggu 8 T-08.01)
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
        'toleransi_menit',
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
            'toleransi_menit' => 'integer',
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
