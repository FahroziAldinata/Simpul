<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSekolah;
use App\Models\Concerns\LogsSimpulActivity;
use Database\Factories\JurusanFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Model Jurusan.
 *
 * Catatan Desain Skema:
 * Kolom `jenjang` sengaja tidak diduplikasi di tabel/model Jurusan karena jenjang sekolah
 * (SMA/SMK) sudah ditentukan secara normal di tabel induk `sekolah` ($this->sekolah->jenjang).
 *
 * @property string $id
 * @property string $sekolah_id
 * @property string $kode
 * @property string $nama
 * @property string|null $bidang_keahlian
 * @property string|null $program_keahlian
 * @property bool $is_aktif
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Jurusan extends Model
{
    /** @use HasFactory<JurusanFactory> */
    use BelongsToSekolah, HasFactory, HasUuids, LogsSimpulActivity;

    protected $table = 'jurusan';

    protected $fillable = [
        'sekolah_id',
        'kode',
        'nama',
        'bidang_keahlian',
        'program_keahlian',
        'is_aktif',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_aktif' => 'boolean',
        ];
    }
}
