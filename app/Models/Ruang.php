<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSekolah;
use App\Models\Concerns\LogsSimpulActivity;
use Database\Factories\RuangFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $sekolah_id
 * @property string $kode
 * @property string $nama
 * @property string $kategori
 * @property int $kapasitas
 * @property string|null $lokasi
 * @property bool $is_aktif
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Ruang extends Model
{
    /** @use HasFactory<RuangFactory> */
    use BelongsToSekolah, HasFactory, HasUuids, LogsSimpulActivity;

    protected $table = 'ruang';

    protected $fillable = [
        'sekolah_id',
        'kode',
        'nama',
        'kategori',
        'kapasitas',
        'lokasi',
        'is_aktif',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kapasitas' => 'integer',
            'is_aktif' => 'boolean',
        ];
    }
}
