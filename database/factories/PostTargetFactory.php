<?php

namespace Database\Factories;

use App\Domain\Content\Enums\PostTargetStatus;
use App\Models\Channel;
use App\Models\Post;
use App\Models\PostTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostTarget>
 */
class PostTargetFactory extends Factory
{
    protected $model = PostTarget::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'channel_id' => Channel::factory(),
            'status' => PostTargetStatus::Pending->value,
            'scheduled_at' => now()->addDay(),
            'published_at' => null,
            'platform_options' => null,
        ];
    }
}
