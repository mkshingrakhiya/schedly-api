<?php

namespace Database\Factories;

use App\Domain\Content\Models\Channel;
use App\Models\Platform;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Channel>
 */
class ChannelFactory extends Factory
{
    protected $model = Channel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'platform_id' => Platform::factory(),
            'platform_account_id' => fake()->numerify('##########'),
            'handle' => fake()->unique()->userName(),
            'access_token' => fake()->sha256(),
            'refresh_token' => fake()->optional()->sha256(),
            'token_expires_at' => fake()->optional()->dateTimeBetween('now', '+1 year'),
            'created_by' => User::factory(),
        ];
    }
}
