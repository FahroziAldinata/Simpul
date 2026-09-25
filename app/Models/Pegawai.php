<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSekolah;
use Database\Factories\PegawaiFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $sekolah_id
 * @property int|null $user_id
 * @property string|null $nuptk
 * @property string|null $nip
 * @property string $nama
 * @property string $jenis
 * @property string $status_kepegawaian
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Pegawai extends Model
{
    /** @use HasFactory<PegawaiFactory> */
    use \App\Models\Concerns\LogsSimpulActivity, BelongsToSekolah, HasFactory, HasUuids, SoftDeletes;

    protected $table = 'pegawai';

    protected $fillable = [
        'sekolah_id',
        'user_id',
        'nuptk',
        'nip',
        'nama',
        'jenis',
        'status_kepegawaian',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
