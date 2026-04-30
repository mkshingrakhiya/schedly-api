<?php

namespace Tests\Unit\Services\SocialPlatforms\Drivers;

use App\Domain\Content\Models\Channel;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostMedia;
use App\Domain\Content\Models\PostTarget;
use App\Services\SocialPlatforms\Drivers\InstagramPublisher;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InstagramPublisherTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_returns_success_with_external_post_id_on_graph_success(): void
    {
        $publisher = app(InstagramPublisher::class);
        $post = Post::factory()->create([
            'content' => 'Hello Instagram',
            'type' => 'default',
        ]);
        PostMedia::factory()->create([
            'workspace_id' => $post->workspace_id,
            'owner_id' => $post->created_by,
            'post_id' => $post->id,
            'mime_type' => 'image/jpeg',
        ]);
        $channel = Channel::factory()->create([
            'platform_account_id' => 'ig-account-123',
            'access_token' => 'ig-access-token',
        ]);
        $target = PostTarget::factory()->create([
            'post_id' => $post->id,
            'channel_id' => $channel->id,
        ]);

        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/ig-account-123/media')) {
                return Http::response(['id' => 'ig-container-1'], 200);
            }

            if (str_ends_with($request->url(), '/ig-account-123/media_publish')) {
                return Http::response(['id' => 'ig-media-123'], 200);
            }

            return Http::response([], 500);
        });

        $result = $publisher->publish($post, $target);

        $this->assertTrue($result->successful);
        $this->assertSame('ig-media-123', $result->externalPostId);
        $this->assertNull($result->errorCode);
    }

    public function test_it_returns_failure_when_post_has_no_media(): void
    {
        $publisher = app(InstagramPublisher::class);
        $post = Post::factory()->create([
            'content' => 'Text only',
            'type' => 'default',
        ]);
        $channel = Channel::factory()->create([
            'platform_account_id' => 'ig-account-123',
            'access_token' => 'ig-access-token',
        ]);
        $target = PostTarget::factory()->create([
            'post_id' => $post->id,
            'channel_id' => $channel->id,
        ]);

        Http::fake();

        $result = $publisher->publish($post, $target);

        $this->assertFalse($result->successful);
        $this->assertSame('INSTAGRAM_MEDIA_REQUIRED', $result->errorCode);
        $this->assertFalse($result->recoverable);
        Http::assertNothingSent();
    }

    public function test_it_rejects_unsupported_post_type_without_calling_graph_api(): void
    {
        $publisher = app(InstagramPublisher::class);
        $post = Post::factory()->make([
            'content' => 'Unsupported type post',
        ]);
        $post->setRawAttributes([
            ...$post->getAttributes(),
            'type' => 'story',
        ], true);
        $channel = Channel::factory()->create([
            'platform_account_id' => 'ig-story',
            'access_token' => 'ig-access-token',
        ]);
        $target = PostTarget::factory()->create([
            'channel_id' => $channel->id,
        ]);

        Http::fake();

        $result = $publisher->publish($post, $target);

        $this->assertFalse($result->successful);
        $this->assertSame('UNSUPPORTED_POST_TYPE', $result->errorCode);
        $this->assertFalse($result->recoverable);
        Http::assertNothingSent();
    }

    public function test_it_returns_reconnect_required_on_oauth_190(): void
    {
        $publisher = app(InstagramPublisher::class);
        $post = Post::factory()->create([
            'content' => 'Hello Instagram',
            'type' => 'default',
        ]);
        PostMedia::factory()->create([
            'workspace_id' => $post->workspace_id,
            'owner_id' => $post->created_by,
            'post_id' => $post->id,
            'mime_type' => 'image/jpeg',
        ]);
        $channel = Channel::factory()->create([
            'platform_account_id' => 'ig-account-190',
            'access_token' => 'ig-invalid-token',
        ]);
        $target = PostTarget::factory()->create([
            'post_id' => $post->id,
            'channel_id' => $channel->id,
        ]);

        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/ig-account-190/media')) {
                return Http::response([
                    'error' => [
                        'message' => 'Invalid OAuth access token - Cannot parse access token',
                        'type' => 'OAuthException',
                        'code' => 190,
                    ],
                ], 400);
            }

            return Http::response([], 500);
        });

        $result = $publisher->publish($post, $target);

        $this->assertFalse($result->successful);
        $this->assertSame('INSTAGRAM_RECONNECT_REQUIRED', $result->errorCode);
        $this->assertFalse($result->recoverable);
    }
}
