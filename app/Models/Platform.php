<?php

namespace App\Models;

use Database\Factories\PlatformFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 */
class Platform extends Model
{
    /** @use HasFactory<PlatformFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'name',
    ];

    // TODO: Move to HasSlug trait
    public static function findBySlug(string $slug): ?self
    {
        return static::query()->where('slug', $slug)->first();
    }
}
