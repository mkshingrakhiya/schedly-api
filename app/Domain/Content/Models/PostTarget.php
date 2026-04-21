<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Enums\PostTargetStatus;
use App\Models\Concerns\HasUuid;
use Database\Factories\PostTargetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $post_id
 * @property int $channel_id
 * @property PostTargetStatus $status
 * @property Carbon $scheduled_at
 * @property Carbon|null $published_at
 * @property array<string, mixed>|null $platform_options
 */
class PostTarget extends Model
{
    /** @use HasFactory<PostTargetFactory> */
    use HasFactory, HasUuid;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'post_id',
        'channel_id',
        'status',
        'scheduled_at',
        'published_at',
        'platform_options',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PostTargetStatus::class,
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'platform_options' => 'array',
        ];
    }

    protected static function newFactory(): PostTargetFactory
    {
        return PostTargetFactory::new();
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return BelongsTo<Channel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
