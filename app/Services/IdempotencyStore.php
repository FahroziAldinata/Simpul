<?php

namespace App\Services;

use App\Models\IdempotencyKey;

/**
 * IdempotencyStore — manajemen penyimpanan dan retrieval respon idempoten (T-10.06 & ADR-007).
 *
 * Menjamin bahwa request dengan Idempotency-Key yang sama dalam periode TTL (default 7 hari)
 * mengembalikan hasil yang identik tanpa mengeksekusi ulang operasi di database.
 */
class IdempotencyStore
{
    public const DEFAULT_TTL_DAYS = 7;

    /**
     * Cari respon yang sudah tersimpan untuk key dan sekolah tertentu.
     *
     * @return array{status_code: int, response_body: array<string, mixed>}|null
     */
    public function find(string $key, string $sekolahId): ?array
    {
        $record = IdempotencyKey::where('key', $key)
            ->where('sekolah_id', $sekolahId)
            ->where('expires_at', '>', now())
            ->first();

        if (! $record || $record->status_code === null || $record->response_body === null) {
            return null;
        }

        return [
            'status_code' => $record->status_code,
            'response_body' => $record->response_body,
        ];
    }

    /**
     * Simpan respon request ke tabel idempotency_keys.
     *
     * @param  array<string, mixed>  $responseBody
     */
    public function store(
        string $key,
        string $sekolahId,
        string $endpoint,
        int $statusCode,
        array $responseBody,
        int $ttlDays = self::DEFAULT_TTL_DAYS,
    ): void {
        IdempotencyKey::updateOrCreate(
            ['key' => $key],
            [
                'sekolah_id' => $sekolahId,
                'endpoint' => $endpoint,
                'status_code' => $statusCode,
                'response_body' => $responseBody,
                'expires_at' => now()->addDays($ttlDays),
            ]
        );
    }

    /**
     * Bersihkan record yang sudah kedaluwarsa.
     */
    public function bersihkanKedaluwarsa(): int
    {
        return IdempotencyKey::where('expires_at', '<=', now())->delete();
    }
}
