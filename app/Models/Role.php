<?php

namespace App\Models;

use App\Enums\RoleSlug;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property string $slug
 * @property string $name
 * @property string|null $description
 */
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory, HasUuid;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'name',
        'description',
    ];

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @param  RoleSlug|non-falsy-string  $slug
     */
    public static function findBySlug(RoleSlug|string $slug): ?self
    {
        $key = $slug instanceof RoleSlug ? $slug->value : $slug;

        return static::query()->where('slug', $key)->first();
    }

    /**
     * @param  RoleSlug|non-falsy-string  $slug
     */
    public static function findBySlugOrFail(RoleSlug|string $slug): self
    {
        $key = $slug instanceof RoleSlug ? $slug->value : $slug;

        return static::query()->where('slug', $key)->firstOrFail();
    }
}
