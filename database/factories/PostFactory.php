<?php

namespace Database\Factories;

use App\Domain\Content\Enums\PostStatus;
use App\Domain\Content\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'created_by' => User::factory(),
            'content' => fake()->paragraph(),
            'status' => PostStatus::Draft,
        ];
    }
}
