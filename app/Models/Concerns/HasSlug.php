<?php

namespace App\Models\Concerns;

trait HasSlug
{
    public static function findBySlug(string $slug): ?self
    {
        return static::query()->where('slug', $slug)->first();
    }

    public static function findBySlugOrFail(string $slug): self
    {
        return static::query()->where('slug', $slug)->firstOrFail();
    }
}
