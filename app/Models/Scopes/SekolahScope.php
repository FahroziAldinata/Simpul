<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * @implements Scope<Model>
 */
class SekolahScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $sekolahId = session('sekolah_id') ?? (auth()->check() ? auth()->user()->sekolah_id : null);

        if ($sekolahId !== null) {
            $builder->where($model->qualifyColumn('sekolah_id'), $sekolahId);
        }
    }
}
