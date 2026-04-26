<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

trait HasUuid
{
    protected static function bootHasUuid(): void
    {
        static::creating(function (Model $model): void {
            if (blank($model->getAttribute('uuid'))) {
                $model->setAttribute('uuid', Uuid::uuid7()->toString());
            }
        });
    }

    public static function findByUuid(string $uuid): ?self
    {
        return static::query()->where('uuid', $uuid)->first();
    }

    public function initializeHasUuid(): void
    {
        $this->mergeHidden(['id']);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
