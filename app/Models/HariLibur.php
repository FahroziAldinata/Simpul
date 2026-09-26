<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSekolah;
use App\Models\Concerns\LogsSimpulActivity;
use Database\Factories\HariLiburFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $sekolah_id
 * @property Carbon $tanggal_mulai
 * @property Carbon $tanggal_selesai
 * @property string $keterangan
 * @property string $jenis
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class HariLibur extends Model
{
    /** @use HasFactory<HariLiburFactory> */
    use BelongsToSekolah, HasFactory, HasUuids, LogsSimpulActivity;

    protected $table = 'hari_libur';

    protected $fillable = [
        'sekolah_id',
        'tanggal_mulai',
        'tanggal_selesai',
        'keterangan',
        'jenis',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
        ];
    }
}
