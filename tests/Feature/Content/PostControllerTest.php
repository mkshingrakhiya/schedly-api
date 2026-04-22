<?php

namespace Tests\Feature\Content;

use App\Domain\Content\Enums\PostStatus;
use App\Domain\Content\Models\Channel;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostMedia;
use App\Domain\Content\Models\PostTarget;
use App\Models\Platform;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PostControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_cannot_access_posts(): void
    {
        [$workspace] = $this->workspaceChannelAndOwner();

        $this->getJson('/api/v1/posts', $this->workspaceHeader($workspace->uuid))
            ->assertUnauthorized();

        $post = Post::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $workspace->owner_id,
        ]);

        $this
            ->withHeaders($this->workspaceHeader($workspace->uuid))
            ->patchJson('/api/v1/posts/'.$post->uuid, [
                'content' => 'try',
            ])
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

        $this
            ->patchJson('/api/v1/posts/'.$post->uuid, ['content' => 'x'])
            ->assertStatus(400);
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

        $response = $this
            ->withHeaders($this->workspaceHeader($workspace->uuid))
            ->postJson('/api/v1/posts', [
                'content' => 'Draft body',
                'status' => PostStatus::Scheduled->value,
                'targets' => [
                    [
                        'channel_uuid' => $channel->uuid,
                        'scheduled_at' => $scheduledAt,
                        'platform_options' => ['foo' => 'bar'],
                    ],
                ],
            ])
            ->assertCreated()
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

    public function test_store_validates_required_content(): void
    {
        [$workspace, $_channel, $owner] = $this->workspaceChannelAndOwner();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/posts', [], $this->workspaceHeader($workspace->uuid))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
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

        $this
            ->withHeaders($this->workspaceHeader($workspaceA->uuid))
            ->getJson('/api/v1/posts/'.$post->uuid)
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

    public function test_update_requires_workspace_header(): void
    {
        [$workspace, $_channel, $owner] = $this->workspaceChannelAndOwner();
        Sanctum::actingAs($owner);

        $post = Post::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
        ]);

        $this
            ->withHeaders(['Accept' => 'application/json'])
            ->patchJson('/api/v1/posts/'.$post->uuid, ['content' => 'only with header'])
            ->assertStatus(400);
    }

    public function test_update_returns_404_when_post_belongs_to_another_workspace(): void
    {
        [$workspaceA] = $this->workspaceChannelAndOwner();
        [$workspaceB, $_channelB, $ownerB] = $this->workspaceChannelAndOwner();

        $post = Post::factory()->create([
            'workspace_id' => $workspaceB->id,
            'created_by' => $ownerB->id,
        ]);

        Sanctum::actingAs($ownerB);

        $this
            ->withHeaders($this->workspaceHeader($workspaceA->uuid))
            ->patchJson('/api/v1/posts/'.$post->uuid, ['content' => 'nope'])
            ->assertNotFound();
    }

    public function test_owner_can_partially_update_content_without_changing_status(): void
    {
        [$workspace, $_channel, $owner] = $this->workspaceChannelAndOwner();
        Sanctum::actingAs($owner);

        $post = Post::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
            'content' => 'Original',
            'status' => PostStatus::Scheduled,
        ]);

        $this
            ->withHeaders($this->workspaceHeader($workspace->uuid))
            ->patchJson('/api/v1/posts/'.$post->uuid, ['content' => 'Only body'])
            ->assertOk()
            ->assertJsonPath('data.content', 'Only body')
            ->assertJsonPath('data.status', PostStatus::Scheduled->value);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'content' => 'Only body',
            'status' => PostStatus::Scheduled->value,
        ]);
    }

    public function test_owner_can_replace_targets_on_update(): void
    {
        [$workspace, $channelA, $owner] = $this->workspaceChannelAndOwner();
        $platform = Platform::query()->where('slug', 'facebook')->firstOrFail();
        $channelB = Channel::factory()->create([
            'workspace_id' => $workspace->id,
            'platform_id' => $platform->id,
            'created_by' => $owner->id,
        ]);

        Sanctum::actingAs($owner);

        $post = Post::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
        ]);

        PostTarget::factory()->create([
            'post_id' => $post->id,
            'channel_id' => $channelA->id,
            'scheduled_at' => now()->addDays(3),
        ]);

        $scheduledAt = now()->addWeek()->startOfSecond()->toISOString();

        $this
            ->withHeaders($this->workspaceHeader($workspace->uuid))
            ->patchJson('/api/v1/posts/'.$post->uuid, [
                'targets' => [
                    [
                        'channel_uuid' => $channelB->uuid,
                        'scheduled_at' => $scheduledAt,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.targets.0.channel.uuid', $channelB->uuid);

        $this->assertDatabaseMissing('post_targets', ['channel_id' => $channelA->id]);
        $this->assertDatabaseHas('post_targets', [
            'post_id' => $post->id,
            'channel_id' => $channelB->id,
        ]);
        $this->assertSame(1, PostTarget::query()->where('post_id', $post->id)->count());
    }

    public function test_update_validates_post_status_enum(): void
    {
        [$workspace, $_channel, $owner] = $this->workspaceChannelAndOwner();
        Sanctum::actingAs($owner);

        $post = Post::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
        ]);

        $this
            ->withHeaders($this->workspaceHeader($workspace->uuid))
            ->patchJson('/api/v1/posts/'.$post->uuid, [
                'status' => 'not-a-valid-status',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_update_validates_target_channel_must_belong_to_workspace(): void
    {
        [$workspaceA, $_channelA, $ownerA] = $this->workspaceChannelAndOwner();
        [$workspaceB, $channelB] = $this->workspaceChannelAndOwner();

        Sanctum::actingAs($ownerA);

        $post = Post::factory()->create([
            'workspace_id' => $workspaceA->id,
            'created_by' => $ownerA->id,
        ]);

        $this
            ->withHeaders($this->workspaceHeader($workspaceA->uuid))
            ->patchJson('/api/v1/posts/'.$post->uuid, [
                'targets' => [
                    [
                        'channel_uuid' => $channelB->uuid,
                        'scheduled_at' => now()->addDay()->toISOString(),
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['targets.0.channel_uuid']);
    }

    public function test_update_with_empty_payload_does_not_change_attributes(): void
    {
        [$workspace, $_channel, $owner] = $this->workspaceChannelAndOwner();
        Sanctum::actingAs($owner);

        $post = Post::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
            'content' => 'Untouched',
            'status' => PostStatus::Scheduled,
        ]);

        $this
            ->withHeaders($this->workspaceHeader($workspace->uuid))
            ->patchJson('/api/v1/posts/'.$post->uuid, [])
            ->assertOk()
            ->assertJsonPath('data.content', 'Untouched')
            ->assertJsonPath('data.status', PostStatus::Scheduled->value);
    }

    public function test_owner_can_delete_post(): void
    {
        [$workspace, $_channel, $owner] = $this->workspaceChannelAndOwner();
        Sanctum::actingAs($owner);

        $post = Post::factory()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
        ]);

        $this
            ->withHeaders($this->workspaceHeader($workspace->uuid))
            ->deleteJson('/api/v1/posts/'.$post->uuid)
            ->assertNoContent();

        $this->assertSoftDeleted($post);
    }

    public function test_store_links_media_uuids(): void
    {
        Storage::fake('public');

        [$workspace, $channel, $owner] = $this->workspaceChannelAndOwner();
        Sanctum::actingAs($owner);

        $mediaUuid = $this
            ->withHeaders(array_merge($this->workspaceHeader($workspace->uuid), ['Accept' => 'application/json']))
            ->post('/api/v1/posts/media/upload', [
                'file' => UploadedFile::fake()->create('photo.jpg', 2048, 'image/jpeg'),
            ])
            ->assertCreated()
            ->json('data.uuid');

        $scheduledAt = now()->addDay()->startOfSecond()->toISOString();

        $response = $this
            ->withHeaders($this->workspaceHeader($workspace->uuid))
            ->postJson('/api/v1/posts', [
                'content' => 'Body',
                'targets' => [
                    [
                        'channel_uuid' => $channel->uuid,
                        'scheduled_at' => $scheduledAt,
                    ],
                ],
                'media_uuids' => [$mediaUuid],
            ])
            ->assertCreated()
            ->assertJsonPath('data.media.0.uuid', $mediaUuid);

        $post = Post::query()->where('uuid', $response->json('data.uuid'))->firstOrFail();
        $this->assertDatabaseHas('post_media', [
            'uuid' => $mediaUuid,
            'post_id' => $post->id,
        ]);
    }

    public function test_store_rejects_media_uuid_from_other_workspace(): void
    {
        [$workspaceA, $channelA, $ownerA] = $this->workspaceChannelAndOwner();
        [$workspaceB] = $this->workspaceChannelAndOwner();

        $foreignMedia = PostMedia::factory()->create([
            'workspace_id' => $workspaceB->id,
            'owner_id' => $workspaceB->owner_id,
            'post_id' => null,
        ]);

        Sanctum::actingAs($ownerA);
        $scheduledAt = now()->addDay()->startOfSecond()->toISOString();

        $this
            ->withHeaders($this->workspaceHeader($workspaceA->uuid))
            ->postJson('/api/v1/posts', [
                'content' => 'Body',
                'targets' => [
                    [
                        'channel_uuid' => $channelA->uuid,
                        'scheduled_at' => $scheduledAt,
                    ],
                ],
                'media_uuids' => [$foreignMedia->uuid],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['media_uuids.0']);
    }

    public function test_store_rejects_media_already_linked_to_another_post(): void
    {
        Storage::fake('public');

        [$workspace, $channel, $owner] = $this->workspaceChannelAndOwner();
        Sanctum::actingAs($owner);

        $mediaUuid = $this
            ->withHeaders(array_merge($this->workspaceHeader($workspace->uuid), ['Accept' => 'application/json']))
            ->post('/api/v1/posts/media/upload', [
                'file' => UploadedFile::fake()->create('photo.jpg', 2048, 'image/jpeg'),
            ])
            ->json('data.uuid');

        $scheduledAt = now()->addDay()->startOfSecond()->toISOString();
        $this
            ->withHeaders($this->workspaceHeader($workspace->uuid))
            ->postJson('/api/v1/posts', [
                'content' => 'First',
                'targets' => [
                    [
                        'channel_uuid' => $channel->uuid,
                        'scheduled_at' => $scheduledAt,
                    ],
                ],
                'media_uuids' => [$mediaUuid],
            ])
            ->assertCreated();

        $this
            ->withHeaders($this->workspaceHeader($workspace->uuid))
            ->postJson('/api/v1/posts', [
                'content' => 'Second',
                'targets' => [
                    [
                        'channel_uuid' => $channel->uuid,
                        'scheduled_at' => $scheduledAt,
                    ],
                ],
                'media_uuids' => [$mediaUuid],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['media_uuids']);
    }

    public function test_update_sync_media_uuids_removes_unlisted_media(): void
    {
        Storage::fake('public');

        [$workspace, $channel, $owner] = $this->workspaceChannelAndOwner();
        Sanctum::actingAs($owner);

        $headers = array_merge($this->workspaceHeader($workspace->uuid), ['Accept' => 'application/json']);

        $uuidA = $this->withHeaders($headers)->post('/api/v1/posts/media/upload', [
            'file' => UploadedFile::fake()->create('a.jpg', 100, 'image/jpeg'),
        ])->json('data.uuid');
        $uuidB = $this->withHeaders($headers)->post('/api/v1/posts/media/upload', [
            'file' => UploadedFile::fake()->create('b.jpg', 100, 'image/jpeg'),
        ])->json('data.uuid');

        $scheduledAt = now()->addDay()->startOfSecond()->toISOString();
        $postResponse = $this
            ->withHeaders($this->workspaceHeader($workspace->uuid))
            ->postJson('/api/v1/posts', [
                'content' => 'Post',
                'targets' => [
                    [
                        'channel_uuid' => $channel->uuid,
                        'scheduled_at' => $scheduledAt,
                    ],
                ],
                'media_uuids' => [$uuidA, $uuidB],
            ])
            ->assertCreated();

        $postUuid = $postResponse->json('data.uuid');
        $mediaA = PostMedia::query()->where('uuid', $uuidA)->firstOrFail();
        $pathB = PostMedia::query()->where('uuid', $uuidB)->value('path');
        $this->assertIsString($pathB);

        $this
            ->withHeaders($this->workspaceHeader($workspace->uuid))
            ->patchJson('/api/v1/posts/'.$postUuid, [
                'media_uuids' => [$uuidA],
            ])
            ->assertOk()
            ->assertJsonPath('data.media.0.uuid', $uuidA);

        $this->assertDatabaseHas('post_media', ['uuid' => $uuidA]);
        $this->assertDatabaseMissing('post_media', ['uuid' => $uuidB]);
        Storage::disk('public')->assertMissing($pathB);
    }

    public function test_update_without_media_uuids_does_not_remove_media(): void
    {
        Storage::fake('public');

        [$workspace, $channel, $owner] = $this->workspaceChannelAndOwner();
        Sanctum::actingAs($owner);

        $headers = array_merge($this->workspaceHeader($workspace->uuid), ['Accept' => 'application/json']);
        $mediaUuid = $this->withHeaders($headers)->post('/api/v1/posts/media/upload', [
            'file' => UploadedFile::fake()->create('a.jpg', 100, 'image/jpeg'),
        ])->json('data.uuid');

        $scheduledAt = now()->addDay()->startOfSecond()->toISOString();
        $postUuid = $this
            ->withHeaders($this->workspaceHeader($workspace->uuid))
            ->postJson('/api/v1/posts', [
                'content' => 'Post',
                'targets' => [
                    [
                        'channel_uuid' => $channel->uuid,
                        'scheduled_at' => $scheduledAt,
                    ],
                ],
                'media_uuids' => [$mediaUuid],
            ])
            ->json('data.uuid');

        $this
            ->withHeaders($this->workspaceHeader($workspace->uuid))
            ->patchJson('/api/v1/posts/'.$postUuid, [
                'content' => 'Updated only',
            ])
            ->assertOk();

        $this->assertDatabaseHas('post_media', ['uuid' => $mediaUuid]);
    }

    public function test_delete_post_removes_linked_media_and_files(): void
    {
        Storage::fake('public');

        [$workspace, $channel, $owner] = $this->workspaceChannelAndOwner();
        Sanctum::actingAs($owner);

        $headers = array_merge($this->workspaceHeader($workspace->uuid), ['Accept' => 'application/json']);
        $this->withHeaders($headers)->post('/api/v1/posts/media/upload', [
            'file' => UploadedFile::fake()->create('a.jpg', 100, 'image/jpeg'),
        ])->assertCreated();

        $media = PostMedia::query()->where('workspace_id', $workspace->id)->firstOrFail();
        $path = $media->path;

        $scheduledAt = now()->addDay()->startOfSecond()->toISOString();
        $postUuid = $this
            ->withHeaders($this->workspaceHeader($workspace->uuid))
            ->postJson('/api/v1/posts', [
                'content' => 'Post',
                'targets' => [
                    [
                        'channel_uuid' => $channel->uuid,
                        'scheduled_at' => $scheduledAt,
                    ],
                ],
                'media_uuids' => [$media->uuid],
            ])
            ->json('data.uuid');

        $this
            ->withHeaders($this->workspaceHeader($workspace->uuid))
            ->deleteJson('/api/v1/posts/'.$postUuid)
            ->assertNoContent();

        $this->assertDatabaseMissing('post_media', ['id' => $media->id]);
        Storage::disk('public')->assertMissing($path);
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
}
