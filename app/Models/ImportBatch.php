<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSekolah;
use Database\Factories\ImportBatchFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $sekolah_id
 * @property int $user_id
 * @property string $tipe
 * @property string $nama_file
 * @property string $path
 * @property array<string, string>|null $pemetaan_kolom
 * @property int $total_baris
 * @property int $valid
 * @property int $peringatan
 * @property int $gagal
 * @property int $dibuat
 * @property int $diperbarui
 * @property int $dilewati
 * @property string $status
 * @property Carbon|null $dapat_dirollback_hingga
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ImportBatch extends Model
{
    /** @use HasFactory<ImportBatchFactory> */
    use BelongsToSekolah, HasFactory, HasUuids;

    protected $table = 'import_batches';

    protected $fillable = [
        'sekolah_id',
        'user_id',
        'tipe',
        'nama_file',
        'path',
        'pemetaan_kolom',
        'total_baris',
        'valid',
        'peringatan',
        'gagal',
        'dibuat',
        'diperbarui',
        'dilewati',
        'status',
        'dapat_dirollback_hingga',
    ];

    protected function casts(): array
    {
        return [
            'pemetaan_kolom' => 'array',
            'total_baris' => 'integer',
            'valid' => 'integer',
            'peringatan' => 'integer',
            'gagal' => 'integer',
            'dibuat' => 'integer',
            'diperbarui' => 'integer',
            'dilewati' => 'integer',
            'dapat_dirollback_hingga' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ImportRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class, 'import_batch_id');
    }
}
