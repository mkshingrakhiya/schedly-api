<?php

namespace Tests\Feature\Content;

use App\Domain\Content\Enums\PostStatus;
use App\Domain\Content\Models\Channel;
use App\Domain\Content\Models\Post;
use App\Models\Platform;
use App\Models\User;
use App\Models\Workspace;
use Database\Factories\WorkspaceMemberFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PostControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array{Workspace, Channel, User}
     */
    private function workspaceChannelAndOwner(): array
    {
        $workspace = Workspace::factory()->create();
        $platform = Platform::query()->where('slug', 'instagram')->firstOrFail();
        $channel = Channel::factory()->create([
            'workspace_id' => $workspace->id,
            'platform_id' => $platform->id,
            'created_by' => $workspace->owner_id,
        ]);
        $owner = User::query()->findOrFail($workspace->owner_id);

        return [$workspace, $channel, $owner];
    }

    public function test_guest_cannot_access_posts(): void
    {
        [$workspace] = $this->workspaceChannelAndOwner();

        $this->getJson('/api/v1/posts', $this->workspaceHeader($workspace->uuid))
            ->assertUnauthorized();

        $post = Post::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $workspace->owner_id,
        ]);

        $this->getJson('/api/v1/posts/'.$post->uuid, $this->workspaceHeader($workspace->uuid))
            ->assertUnauthorized();
    }

    public function test_posts_endpoints_require_workspace_header(): void
    {
        [$workspace, $_channel, $owner] = $this->workspaceChannelAndOwner();
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/posts')->assertStatus(400);

        $post = Post::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
        ]);

        $this->getJson('/api/v1/posts/'.$post->uuid)->assertStatus(400);
    }

    public function test_workspace_member_can_list_posts(): void
    {
        [$workspace, $_channel, $owner] = $this->workspaceChannelAndOwner();
        Sanctum::actingAs($owner);

        Post::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
            'content' => 'Hello',
        ]);

        $response = $this->getJson('/api/v1/posts', $this->workspaceHeader($workspace->uuid));

        $response->assertOk()->assertJsonPath('data.0.content', 'Hello');
    }

    public function test_non_member_cannot_list_posts(): void
    {
        [$workspace] = $this->workspaceChannelAndOwner();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/posts', $this->workspaceHeader($workspace->uuid))
            ->assertForbidden();
    }

    public function test_owner_can_create_post_with_targets(): void
    {
        [$workspace, $channel, $owner] = $this->workspaceChannelAndOwner();
        Sanctum::actingAs($owner);

        $scheduledAt = now()->addDay()->startOfSecond()->toISOString();

        $response = $this->postJson('/api/v1/posts', [
            'content' => 'Draft body',
            'status' => PostStatus::Scheduled->value,
            'targets' => [
                [
                    'channelUuid' => $channel->uuid,
                    'scheduledAt' => $scheduledAt,
                    'platformOptions' => ['foo' => 'bar'],
                ],
            ],
        ], $this->workspaceHeader($workspace->uuid));

        $response->assertCreated()
            ->assertJsonPath('data.content', 'Draft body')
            ->assertJsonPath('data.status', PostStatus::Scheduled->value)
            ->assertJsonPath('data.targets.0.channel.uuid', $channel->uuid)
            ->assertJsonPath('data.targets.0.platformOptions.foo', 'bar');

        $uuid = $response->json('data.uuid');
        $this->assertNotNull($uuid);
        $this->assertDatabaseHas('posts', [
            'uuid' => $uuid,
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
        ]);
        $this->assertDatabaseHas('post_targets', [
            'channel_id' => $channel->id,
        ]);
    }

    public function test_member_without_owner_role_cannot_create_posts(): void
    {
        [$workspace, $_channel, $owner] = $this->workspaceChannelAndOwner();
        $member = User::factory()->create();
        WorkspaceMemberFactory::new()->member()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
        ]);
        Sanctum::actingAs($member);

        $this->postJson('/api/v1/posts', [
            'content' => 'Nope',
        ], $this->workspaceHeader($workspace->uuid))
            ->assertForbidden();

        $this->assertDatabaseMissing('posts', [
            'content' => 'Nope',
            'workspace_id' => $workspace->id,
        ]);
    }

    public function test_store_validates_required_content(): void
    {
        [$workspace, $_channel, $owner] = $this->workspaceChannelAndOwner();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/posts', [], $this->workspaceHeader($workspace->uuid))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
    }

    public function test_member_can_view_post(): void
    {
        [$workspace, $_channel, $owner] = $this->workspaceChannelAndOwner();
        $member = User::factory()->create();
        WorkspaceMemberFactory::new()->member()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
        ]);

        $post = Post::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
            'content' => 'Shared',
        ]);

        Sanctum::actingAs($member);

        $this->getJson('/api/v1/posts/'.$post->uuid, $this->workspaceHeader($workspace->uuid))
            ->assertOk()
            ->assertJsonPath('data.content', 'Shared');
    }

    public function test_show_returns_404_when_post_belongs_to_another_workspace(): void
    {
        [$workspaceA] = $this->workspaceChannelAndOwner();
        [$workspaceB, $_channelB, $ownerB] = $this->workspaceChannelAndOwner();

        $post = Post::factory()->create([
            'workspace_id' => $workspaceB->id,
            'created_by' => $ownerB->id,
        ]);

        Sanctum::actingAs($ownerB);

        $this->getJson('/api/v1/posts/'.$post->uuid, $this->workspaceHeader($workspaceA->uuid))
            ->assertNotFound();
    }

    public function test_non_member_cannot_view_post(): void
    {
        [$workspace, $_channel, $owner] = $this->workspaceChannelAndOwner();
        $post = Post::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/posts/'.$post->uuid, $this->workspaceHeader($workspace->uuid))
            ->assertForbidden();
    }

    public function test_owner_can_update_post(): void
    {
        [$workspace, $_channel, $owner] = $this->workspaceChannelAndOwner();
        Sanctum::actingAs($owner);

        $post = Post::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
            'content' => 'Old',
            'status' => PostStatus::Scheduled,
        ]);

        $this->patchJson('/api/v1/posts/'.$post->uuid, [
            'content' => 'New',
            'status' => PostStatus::Published->value,
        ], $this->workspaceHeader($workspace->uuid))
            ->assertOk()
            ->assertJsonPath('data.content', 'New')
            ->assertJsonPath('data.status', PostStatus::Published->value);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'content' => 'New',
            'status' => PostStatus::Published->value,
        ]);
    }

    public function test_member_cannot_update_post(): void
    {
        [$workspace, $_channel, $owner] = $this->workspaceChannelAndOwner();
        $member = User::factory()->create();
        WorkspaceMemberFactory::new()->member()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
        ]);

        $post = Post::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
        ]);

        Sanctum::actingAs($member);

        $this->patchJson('/api/v1/posts/'.$post->uuid, [
            'content' => 'Hacked',
        ], $this->workspaceHeader($workspace->uuid))
            ->assertForbidden();

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'content' => $post->content,
        ]);
    }

    public function test_owner_can_delete_post(): void
    {
        [$workspace, $_channel, $owner] = $this->workspaceChannelAndOwner();
        Sanctum::actingAs($owner);

        $post = Post::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
        ]);

        $this->deleteJson('/api/v1/posts/'.$post->uuid, [], $this->workspaceHeader($workspace->uuid))
            ->assertNoContent();

        $this->assertSoftDeleted($post);
    }

    public function test_member_cannot_delete_post(): void
    {
        [$workspace, $_channel, $owner] = $this->workspaceChannelAndOwner();
        $member = User::factory()->create();
        WorkspaceMemberFactory::new()->member()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
        ]);

        $post = Post::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
        ]);

        Sanctum::actingAs($member);

        $this->deleteJson('/api/v1/posts/'.$post->uuid, [], $this->workspaceHeader($workspace->uuid))
            ->assertForbidden();

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function mutationMethods(): iterable
    {
        yield 'store' => ['post'];
        yield 'update' => ['patch'];
        yield 'destroy' => ['delete'];
    }

    #[DataProvider('mutationMethods')]
    public function test_mutations_require_workspace_member(string $method): void
    {
        [$workspace] = $this->workspaceChannelAndOwner();
        $intruder = User::factory()->create();
        Sanctum::actingAs($intruder);

        $post = Post::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $workspace->owner_id,
        ]);

        $headers = $this->workspaceHeader($workspace->uuid);

        match ($method) {
            'post' => $this->postJson('/api/v1/posts', ['content' => 'x'], $headers)->assertForbidden(),
            'patch' => $this->patchJson('/api/v1/posts/'.$post->uuid, ['content' => 'y'], $headers)->assertForbidden(),
            'delete' => $this->deleteJson('/api/v1/posts/'.$post->uuid, [], $headers)->assertForbidden(),
            default => $this->fail('unexpected method'),
        };
    }
}
