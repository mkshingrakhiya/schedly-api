<?php

namespace Database\Factories;

use App\Domain\Content\Models\PlatformOAuthConnection;
use App\Models\Platform;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformOAuthConnection>
 */
class PlatformOAuthConnectionFactory extends Factory
{
    protected $model = PlatformOAuthConnection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'platform_id' => Platform::factory(),
            'provider_user_id' => fake()->numerify('###############'),
            'access_token' => fake()->sha256(),
            'expires_at' => now()->addDays(59),
            'created_by' => User::factory(),
        ];
    }
}
