<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Database\Factories\PlatformFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 */
#[UseFactory(PlatformFactory::class)]
class Platform extends Model
{
    use HasFactory, HasSlug;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'name',
    ];
}
