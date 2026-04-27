<?php

namespace Database\Factories;

use App\Domain\Content\Models\PlatformOAuthConnectionState;
use App\Models\Platform;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformOAuthConnectionState>
 */
class PlatformOAuthConnectionStateFactory extends Factory
{
    protected $model = PlatformOAuthConnectionState::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'platform_id' => Platform::factory(),
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
        ];
    }
}
