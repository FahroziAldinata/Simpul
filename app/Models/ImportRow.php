<?php

namespace App\Models;

use Database\Factories\ImportRowFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $import_batch_id
 * @property int $nomor_baris
 * @property array<string, mixed> $data_mentah
 * @property array<string, mixed>|null $data_bersih
 * @property string $status
 * @property array<string, string>|null $errors
 * @property string|null $aksi_duplikat
 * @property string|null $model_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ImportRow extends Model
{
    /** @use HasFactory<ImportRowFactory> */
    use HasFactory, HasUuids;

    protected $table = 'import_rows';

    protected $fillable = [
        'import_batch_id',
        'nomor_baris',
        'data_mentah',
        'data_bersih',
        'status',
        'errors',
        'aksi_duplikat',
        'model_id',
    ];

    protected function casts(): array
    {
        return [
            'nomor_baris' => 'integer',
            'data_mentah' => 'array',
            'data_bersih' => 'array',
            'errors' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ImportBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }
}
