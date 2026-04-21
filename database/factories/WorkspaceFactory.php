<?php

namespace Database\Factories;

use App\Domain\Workspaces\Enums\WorkspaceMemberRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'owner_id' => User::factory(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Workspace $workspace): void {
            WorkspaceMember::query()->firstOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'user_id' => $workspace->owner_id,
                ],
                ['role' => WorkspaceMemberRole::Owner],
            );
        });
    }
}
