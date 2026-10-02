<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Model IdempotencyKey.
 *
 * Menyimpan respon dari request ber-Idempotency-Key untuk menjamin
 * operasi identik hanya dieksekusi sekali di server (T-10.06 & ADR-007).
 *
 * @property string $key
 * @property string $sekolah_id
 * @property string $endpoint
 * @property array<string, mixed>|null $response_body
 * @property int|null $status_code
 * @property Carbon $expires_at
 */
class IdempotencyKey extends Model
{
    protected $table = 'idempotency_keys';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'key',
        'sekolah_id',
        'endpoint',
        'response_body',
        'status_code',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response_body' => 'array',
            'status_code' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Sekolah, $this>
     */
    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }
}
