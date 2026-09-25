<?php

namespace App\Models\Concerns;

use App\Models\Scopes\SekolahScope;
use App\Models\Sekolah;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToSekolah
{
    /**
     * Boot the BelongsToSekolah trait for a model.
     */
    protected static function bootBelongsToSekolah(): void
    {
        static::addGlobalScope(new SekolahScope);

        static::creating(function (self $model): void {
            if (empty($model->sekolah_id)) {
                $sekolahId = session('sekolah_id') ?? (auth()->check() ? auth()->user()->sekolah_id : null);
                if ($sekolahId !== null) {
                    $model->sekolah_id = $sekolahId;
                }
            }
        });
    }

    /**
     * @return BelongsTo<Sekolah, $this>
     */
    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class);
    }
}
