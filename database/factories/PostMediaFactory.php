<?php

namespace Database\Factories;

use App\Domain\Content\Models\PostMedia;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostMedia>
 */
class PostMediaFactory extends Factory
{
    protected $model = PostMedia::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'owner_id' => User::factory(),
            'post_id' => null,
            'disk' => 'public',
            'path' => sprintf(config('app.post_media_storage_path'), fake()->uuid()).'/'.fake()->lexify('????').'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(1000, 5_000_000),
            'order' => 0,
        ];
    }
}
