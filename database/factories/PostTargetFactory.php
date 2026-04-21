<?php

namespace Database\Factories;

use App\Domain\Content\Enums\PostTargetStatus;
use App\Domain\Content\Models\Channel;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostTarget;
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
            'status' => PostTargetStatus::Pending,
            'scheduled_at' => fake()->dateTimeBetween('now', '+1 month'),
            'published_at' => null,
            'platform_options' => null,
        ];
    }
}
