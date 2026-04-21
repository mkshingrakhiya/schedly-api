<?php

namespace Tests\Feature\Content;

use App\Domain\Content\Enums\PostStatus;
use App\Http\Requests\Api\V1FormRequest;
use App\Models\Channel;
use App\Models\Platform;
use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const V1_POSTS = '/api/v1/posts';

    /**
     * @return array{owner: User, workspace: Workspace}
     */
    private function ownedWorkspace(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        return ['owner' => $owner, 'workspace' => $workspace];
    }

    /**
     * @return array<string, string>
     */
    private function workspaceHeaders(Workspace $workspace): array
    {
        return [V1FormRequest::WORKSPACE_HEADER => $workspace->uuid];
    }

    private function withWorkspace(Workspace $workspace): self
    {
        return $this->withHeaders($this->workspaceHeaders($workspace));
    }

    private function platform(string $slug): Platform
    {
        return Platform::query()->where('slug', $slug)->firstOrFail();
    }

    private function channelInWorkspace(Workspace $workspace, User $actor, Platform $platform): Channel
    {
        return Channel::factory()->create([
            'workspace_id' => $workspace->id,
            'platform_id' => $platform->id,
            'created_by' => $actor->id,
        ]);
    }

    /**
     * @return array{channel_uuid: string, scheduled_at: string, platform_options?: array<string, mixed>}
     */
    private function targetFor(Channel $channel, ?array $platformOptions = null): array
    {
        $row = [
            'channel_uuid' => $channel->uuid,
            'scheduled_at' => now()->addDay()->toISOString(),
        ];

        if ($platformOptions !== null) {
            $row['platform_options'] = $platformOptions;
        }

        return $row;
    }

    /**
     * @param  list<array<string, mixed>>  $targets
     * @return array{content: string, status: string, targets: list<array<string, mixed>>}
     */
    private function storeBody(string $content, array $targets): array
    {
        return [
            'content' => $content,
            'status' => PostStatus::Scheduled->value,
            'targets' => $targets,
        ];
    }

    public function test_guest_cannot_list_posts(): void
    {
        $workspace = Workspace::factory()->create();

        $this->withWorkspace($workspace)
            ->getJson(self::V1_POSTS)
            ->assertUnauthorized();
    }

    public function test_non_member_cannot_list_posts(): void
    {
        $workspace = Workspace::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->withWorkspace($workspace)
            ->getJson(self::V1_POSTS)
            ->assertForbidden();
    }

    public function test_non_member_cannot_create_posts(): void
    {
        ['owner' => $owner, 'workspace' => $workspace] = $this->ownedWorkspace();
        $channel = $this->channelInWorkspace($workspace, $owner, $this->platform('instagram'));

        Sanctum::actingAs(User::factory()->create());

        $this->withWorkspace($workspace)
            ->postJson(self::V1_POSTS, $this->storeBody('Hello', [$this->targetFor($channel)]))
            ->assertForbidden();
    }

    public function test_non_member_cannot_show_post_even_with_workspace_header(): void
    {
        ['owner' => $owner, 'workspace' => $workspace] = $this->ownedWorkspace();
        $post = Post::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->withWorkspace($workspace)
            ->getJson(self::V1_POSTS."/{$post->uuid}")
            ->assertForbidden();
    }

    public function test_owner_can_create_post_with_multiple_targets(): void
    {
        ['owner' => $owner, 'workspace' => $workspace] = $this->ownedWorkspace();
        $channelA = $this->channelInWorkspace($workspace, $owner, $this->platform('instagram'));
        $channelB = $this->channelInWorkspace($workspace, $owner, $this->platform('facebook'));

        Sanctum::actingAs($owner);

        $this->withWorkspace($workspace)
            ->postJson(self::V1_POSTS, $this->storeBody('Multi-channel post', [
                $this->targetFor($channelA, ['ig' => true]),
                $this->targetFor($channelB),
            ]))
            ->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'uuid',
                    'content',
                    'status',
                    'targets',
                ],
            ]);

        $this->assertDatabaseHas('posts', [
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
            'content' => 'Multi-channel post',
        ]);
    }

    public function test_owner_can_show_post_with_header_only_url(): void
    {
        ['owner' => $owner, 'workspace' => $workspace] = $this->ownedWorkspace();
        $post = Post::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
        ]);

        Sanctum::actingAs($owner);

        $this->withWorkspace($workspace)
            ->getJson(self::V1_POSTS."/{$post->uuid}")
            ->assertOk()
            ->assertJsonPath('data.uuid', $post->uuid);
    }

    public function test_show_requires_workspace_header(): void
    {
        ['owner' => $owner, 'workspace' => $workspace] = $this->ownedWorkspace();
        $post = Post::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
        ]);

        Sanctum::actingAs($owner);

        $this->getJson(self::V1_POSTS."/{$post->uuid}")
            ->assertStatus(400);
    }

    public function test_create_rejects_channel_from_other_workspace(): void
    {
        ['owner' => $owner, 'workspace' => $workspace] = $this->ownedWorkspace();
        $other = Workspace::factory()->create();
        $foreignChannel = $this->channelInWorkspace($other, $other->owner, $this->platform('instagram'));

        Sanctum::actingAs($owner);

        $this->withWorkspace($workspace)
            ->postJson(self::V1_POSTS, $this->storeBody('Bad', [$this->targetFor($foreignChannel)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['targets.0.channel_uuid']);
    }

    public function test_post_from_other_workspace_returns_not_found_with_header(): void
    {
        $workspaceA = Workspace::factory()->create();
        $workspaceB = Workspace::factory()->create();
        $postInA = Post::factory()->create([
            'workspace_id' => $workspaceA->id,
            'created_by' => $workspaceA->owner_id,
        ]);

        Sanctum::actingAs($workspaceA->owner);

        $this->withWorkspace($workspaceB)
            ->getJson(self::V1_POSTS."/{$postInA->uuid}")
            ->assertNotFound();
    }

    public function test_soft_deleted_post_not_in_index(): void
    {
        ['owner' => $owner, 'workspace' => $workspace] = $this->ownedWorkspace();
        $post = Post::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
        ]);

        Sanctum::actingAs($owner);

        $this->withWorkspace($workspace)
            ->deleteJson(self::V1_POSTS."/{$post->uuid}")
            ->assertNoContent();

        $this->withWorkspace($workspace)
            ->getJson(self::V1_POSTS)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
