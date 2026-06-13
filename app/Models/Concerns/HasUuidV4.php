<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Ramsey\Uuid\Uuid as RamseyUuid;

/**
 * Generates a random (v4) UUID primary key on model creation.
 *
 * Replaces goldspecdigital/laravel-eloquent-uuid (dropped on the Laravel 11
 * upgrade — it has no L11 release). Behavior is intentionally identical to
 * that package's Uuid trait: a *random* v4 UUID, NOT Laravel's native
 * HasUuids ordered/time-based UUID. Ordered UUIDs would leak the creation
 * order of records — unacceptable for secret-ballot Vote ids.
 */
trait HasUuidV4
{
    protected function keyIsUuid(): bool
    {
        return true;
    }

    public static function bootHasUuidV4(): void
    {
        static::creating(function (self $model): void {
            if ($model->keyIsUuid() && empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = RamseyUuid::uuid4()->toString();
            }
        });
    }
}
