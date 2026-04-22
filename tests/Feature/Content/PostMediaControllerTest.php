<?php

namespace Tests\Feature\Content;

use App\Domain\Content\Models\Channel;
use App\Domain\Content\Models\PostMedia;
use App\Models\Platform;
use App\Models\User;
use App\Models\Workspace;
use App\Support\MediaPathResolver;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostMediaControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('s3');
    }

    public function test_media_upload_guest_is_unauthorized(): void
    {
        [$workspace] = $this->workspaceAndOwner();

        $this
            ->withHeaders($this->apiWorkspaceHeaders($workspace->uuid))
            ->post('/api/v1/posts/media/upload', [
                'file' => $this->fakeJpegUpload(),
            ])
            ->assertUnauthorized();
    }

    public function test_media_upload_requires_workspace_header(): void
    {
        [$workspace, $owner] = $this->workspaceAndOwner();
        Sanctum::actingAs($owner);

        $this
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/v1/posts/media/upload', [
                'file' => $this->fakeJpegUpload(),
            ])
            ->assertStatus(400);
    }

    public function test_media_upload_accepts_jpeg(): void
    {
        [$workspace, $owner] = $this->workspaceAndOwner();
        Sanctum::actingAs($owner);

        $response = $this
            ->withHeaders($this->apiWorkspaceHeaders($workspace->uuid))
            ->post('/api/v1/posts/media/upload', [
                'file' => $this->fakeJpegUpload(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.ownerUuid', $owner->uuid);

        $media = PostMedia::query()
            ->where('uuid', $response->json('data.uuid'))
            ->firstOrFail();

        Storage::disk(config('filesystems.default'))->assertExists($media->path);
        $this->assertNull($media->post_id);
        $this->assertSame($workspace->id, $media->workspace_id);
        $this->assertSame($owner->id, $media->owner_id);
        $this->assertSame('image/jpeg', $media->mime_type);
        $this->assertTrue(Str::startsWith($media->path, MediaPathResolver::workspacePostMediaDirectory($workspace, $media->uuid)));
    }

    public function test_media_upload_accepts_png(): void
    {
        [$workspace, $owner] = $this->workspaceAndOwner();
        Sanctum::actingAs($owner);

        $this
            ->withHeaders($this->apiWorkspaceHeaders($workspace->uuid))
            ->post('/api/v1/posts/media/upload', [
                'file' => UploadedFile::fake()->create('photo.png', 2048, 'image/png'),
            ])
            ->assertCreated();

        $this->assertDatabaseHas('post_media', [
            'workspace_id' => $workspace->id,
            'owner_id' => $owner->id,
            'mime_type' => 'image/png',
        ]);
    }

    public function test_media_upload_accepts_mp4(): void
    {
        [$workspace, $owner] = $this->workspaceAndOwner();
        Sanctum::actingAs($owner);

        $this
            ->withHeaders($this->apiWorkspaceHeaders($workspace->uuid))
            ->post('/api/v1/posts/media/upload', [
                'file' => UploadedFile::fake()->create('clip.mp4', 1024, 'video/mp4'),
            ])
            ->assertCreated();

        $this->assertDatabaseHas('post_media', [
            'workspace_id' => $workspace->id,
            'mime_type' => 'video/mp4',
        ]);
    }

    public function test_media_upload_rejects_invalid_mime_type(): void
    {
        [$workspace, $owner] = $this->workspaceAndOwner();
        Sanctum::actingAs($owner);

        $this
            ->withHeaders($this->apiWorkspaceHeaders($workspace->uuid))
            ->post('/api/v1/posts/media/upload', [
                'file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
            ])
            ->assertUnprocessable();
    }

    public function test_media_upload_non_member_is_forbidden(): void
    {
        [$workspace] = $this->workspaceAndOwner();
        $intruder = User::factory()->create();
        Sanctum::actingAs($intruder);

        $this
            ->withHeaders($this->apiWorkspaceHeaders($workspace->uuid))
            ->post('/api/v1/posts/media/upload', [
                'file' => $this->fakeJpegUpload(),
            ])
            ->assertForbidden();
    }

    public function test_media_delete_removes_uploaded_file(): void
    {
        [$workspace, $owner] = $this->workspaceAndOwner();
        Sanctum::actingAs($owner);

        $this
            ->withHeaders($this->apiWorkspaceHeaders($workspace->uuid))
            ->post('/api/v1/posts/media/upload', [
                'file' => $this->fakeJpegUpload(),
            ])
            ->assertCreated();

        $media = PostMedia::query()->where('workspace_id', $workspace->id)->firstOrFail();
        $path = $media->path;

        $this
            ->withHeaders($this->apiWorkspaceHeaders($workspace->uuid))
            ->deleteJson('/api/v1/posts/media/'.$media->uuid)
            ->assertNoContent();

        $this->assertDatabaseMissing('post_media', ['id' => $media->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_media_delete_wrong_workspace_returns_not_found(): void
    {
        [$workspaceA, $ownerA] = $this->workspaceAndOwner();
        [$workspaceB, $ownerB] = $this->workspaceAndOwner();

        Sanctum::actingAs($ownerB);
        $this
            ->withHeaders($this->apiWorkspaceHeaders($workspaceB->uuid))
            ->post('/api/v1/posts/media/upload', [
                'file' => $this->fakeJpegUpload(),
            ])
            ->assertCreated();

        $mediaB = PostMedia::query()->where('workspace_id', $workspaceB->id)->firstOrFail();

        Sanctum::actingAs($ownerA);
        $this
            ->withHeaders($this->apiWorkspaceHeaders($workspaceA->uuid))
            ->deleteJson('/api/v1/posts/media/'.$mediaB->uuid)
            ->assertNotFound();
    }

    public function test_media_delete_linked_to_post_removes_row_and_file(): void
    {
        [$workspace, $channel, $owner] = $this->workspaceChannelAndOwner();
        Sanctum::actingAs($owner);

        $upload = $this
            ->withHeaders($this->apiWorkspaceHeaders($workspace->uuid))
            ->post('/api/v1/posts/media/upload', [
                'file' => $this->fakeJpegUpload(),
            ])
            ->assertCreated();

        $mediaUuid = $upload->json('data.uuid');

        $scheduledAt = now()->addDay()->startOfSecond()->toISOString();
        $this
            ->withHeaders($this->workspaceHeader($workspace->uuid))
            ->postJson('/api/v1/posts', [
                'content' => 'With media',
                'targets' => [
                    [
                        'channel_uuid' => $channel->uuid,
                        'scheduled_at' => $scheduledAt,
                    ],
                ],
                'media_uuids' => [$mediaUuid],
            ])
            ->assertCreated();

        $media = PostMedia::query()->where('uuid', $mediaUuid)->firstOrFail();
        $path = $media->path;
        $this->assertNotNull($media->post_id);

        $this
            ->withHeaders($this->apiWorkspaceHeaders($workspace->uuid))
            ->deleteJson('/api/v1/posts/media/'.$media->uuid)
            ->assertNoContent();

        $this->assertDatabaseMissing('post_media', ['id' => $media->id]);
        Storage::disk($media->disk)->assertMissing($path);
    }

    public function test_media_attach_links_s3_object(): void
    {
        [$workspace, $owner] = $this->workspaceAndOwner();
        Sanctum::actingAs($owner);

        $path = MediaPathResolver::workspaceUploadPrefix($workspace).'photo.jpg';
        Storage::disk('s3')->put($path, 'fake-bytes');

        $this
            ->withHeaders($this->apiWorkspaceHeaders($workspace->uuid))
            ->postJson('/api/v1/posts/media/attach', [
                'path' => $path,
                'mime_type' => 'image/jpeg',
                'size' => 10_240,
            ])
            ->assertCreated()
            ->assertJsonPath('data.mimeType', 'image/jpeg')
            ->assertJsonPath('data.size', 10_240)
            ->assertJsonPath('data.ownerUuid', $owner->uuid);

        $this->assertDatabaseHas('post_media', [
            'workspace_id' => $workspace->id,
            'owner_id' => $owner->id,
            'post_id' => null,
            'disk' => config('filesystems.default'),
            'path' => $path,
            'mime_type' => 'image/jpeg',
            'size' => 10_240,
        ]);
    }

    public function test_media_attach_rejects_path_outside_expected_prefix(): void
    {
        [$workspace, $owner] = $this->workspaceAndOwner();
        Sanctum::actingAs($owner);

        $this
            ->withHeaders($this->apiWorkspaceHeaders($workspace->uuid))
            ->postJson('/api/v1/posts/media/attach', [
                'path' => 'other/prefix/file.jpg',
                'mime_type' => 'image/jpeg',
                'size' => 100,
            ])
            ->assertUnprocessable();
    }

    public function test_media_attach_rejects_path_with_parent_directory_segments(): void
    {
        [$workspace, $owner] = $this->workspaceAndOwner();
        Sanctum::actingAs($owner);

        $badPath = MediaPathResolver::workspacePostMediaBase($workspace).'/../evil.jpg';

        $this
            ->withHeaders($this->apiWorkspaceHeaders($workspace->uuid))
            ->postJson('/api/v1/posts/media/attach', [
                'path' => $badPath,
                'mime_type' => 'image/jpeg',
                'size' => 100,
            ])
            ->assertUnprocessable();
    }

    public function test_media_attach_rejects_invalid_mime_type(): void
    {
        [$workspace, $owner] = $this->workspaceAndOwner();
        Sanctum::actingAs($owner);

        $path = MediaPathResolver::workspaceUploadPrefix($workspace).'doc.pdf';

        $this
            ->withHeaders($this->apiWorkspaceHeaders($workspace->uuid))
            ->postJson('/api/v1/posts/media/attach', [
                'path' => $path,
                'mime_type' => 'application/pdf',
                'size' => 100,
            ])
            ->assertUnprocessable();
    }

    public function test_media_attach_non_member_is_forbidden(): void
    {
        [$workspace] = $this->workspaceAndOwner();
        $intruder = User::factory()->create();
        Sanctum::actingAs($intruder);

        $path = MediaPathResolver::workspaceUploadPrefix($workspace).'photo.jpg';

        $this
            ->withHeaders($this->apiWorkspaceHeaders($workspace->uuid))
            ->postJson('/api/v1/posts/media/attach', [
                'path' => $path,
                'mime_type' => 'image/jpeg',
                'size' => 100,
            ])
            ->assertForbidden();
    }

    public function test_posts_index_includes_media_after_linking(): void
    {
        [$workspace, $channel, $owner] = $this->workspaceChannelAndOwner();
        Sanctum::actingAs($owner);

        $mediaUuid = $this
            ->withHeaders($this->apiWorkspaceHeaders($workspace->uuid))
            ->post('/api/v1/posts/media/upload', [
                'file' => $this->fakeJpegUpload(),
            ])
            ->assertCreated()
            ->json('data.uuid');

        $scheduledAt = now()->addDay()->startOfSecond()->toISOString();
        $this
            ->withHeaders($this->workspaceHeader($workspace->uuid))
            ->postJson('/api/v1/posts', [
                'content' => 'Hello',
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
            ->getJson('/api/v1/posts', $this->workspaceHeader($workspace->uuid))
            ->assertOk()
            ->assertJsonPath('data.0.media.0.mimeType', 'image/jpeg');
    }

    /**
     * @return array<string, string>
     */
    private function apiWorkspaceHeaders(string $workspaceUuid): array
    {
        return array_merge($this->workspaceHeader($workspaceUuid), [
            'Accept' => 'application/json',
        ]);
    }

    private function fakeJpegUpload(): UploadedFile
    {
        return UploadedFile::fake()->create('photo.jpg', 2048, 'image/jpeg');
    }

    /**
     * @return array{Workspace, User}
     */
    private function workspaceAndOwner(): array
    {
        $workspace = Workspace::factory()->create();
        $owner = User::query()->findOrFail($workspace->owner_id);

        return [$workspace, $owner];
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
