<?php

namespace App\Models\Concerns;

use Illuminate\Support\Collection;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

trait LogsSimpulActivity
{
    use LogsActivity;

    /**
     * Configure activity log options.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Tap activity before saving to redact sensitive attributes.
     */
    public function tapActivity(ActivityContract $activity, string $eventName): void
    {
        if ($activity instanceof Activity && $activity->properties !== null) {
            $sensitiveKeys = [
                'password',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'remember_token',
            ];

            $properties = $activity->properties->toArray();

            foreach (['attributes', 'old'] as $bag) {
                if (isset($properties[$bag]) && is_array($properties[$bag])) {
                    foreach ($sensitiveKeys as $key) {
                        if (array_key_exists($key, $properties[$bag])) {
                            $properties[$bag][$key] = '[REDACTED]';
                        }
                    }
                }
            }

            /** @var Collection<string, mixed> $collection */
            $collection = new Collection($properties);
            $activity->properties = $collection;
        }
    }
}
