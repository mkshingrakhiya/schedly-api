<?php

namespace Tests\Unit\Services\SocialPlatforms\Drivers;

use App\Domain\Content\Models\Channel;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostTarget;
use App\Services\SocialPlatforms\Drivers\FacebookPublisher;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FacebookPublisherTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_returns_success_with_external_post_id_on_graph_success(): void
    {
        $publisher = app(FacebookPublisher::class);
        $post = Post::factory()->create([
            'content' => 'Hello Facebook',
            'type' => 'default',
        ]);
        $channel = Channel::factory()->create([
            'platform_account_id' => 'page-123',
            'access_token' => 'page-access-token',
        ]);
        $target = PostTarget::factory()->create([
            'post_id' => $post->id,
            'channel_id' => $channel->id,
        ]);

        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/page-123/feed')) {
                return Http::response(['id' => 'page-123_post-1'], 200);
            }

            return Http::response([], 500);
        });

        $result = $publisher->publish($post, $target);

        $this->assertTrue($result->successful);
        $this->assertSame('page-123_post-1', $result->externalPostId);
        $this->assertNull($result->errorCode);
    }

    public function test_it_returns_non_recoverable_failure_on_http_400(): void
    {
        $publisher = app(FacebookPublisher::class);
        $post = Post::factory()->create([
            'content' => 'Bad payload',
            'type' => 'default',
        ]);
        $channel = Channel::factory()->create([
            'platform_account_id' => 'page-400',
            'access_token' => 'page-access-token',
        ]);
        $target = PostTarget::factory()->create([
            'post_id' => $post->id,
            'channel_id' => $channel->id,
        ]);

        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/page-400/feed')) {
                return Http::response(['error' => ['message' => 'Invalid parameter']], 400);
            }

            return Http::response([], 500);
        });

        $result = $publisher->publish($post, $target);

        $this->assertFalse($result->successful);
        $this->assertSame('FACEBOOK_HTTP_400', $result->errorCode);
        $this->assertFalse($result->recoverable);
    }

    public function test_it_rejects_unsupported_post_type_without_calling_graph_api(): void
    {
        $publisher = app(FacebookPublisher::class);
        $post = Post::factory()->make([
            'content' => 'Unsupported type post',
        ]);
        $post->setRawAttributes([
            ...$post->getAttributes(),
            'type' => 'story',
        ], true);
        $channel = Channel::factory()->create([
            'platform_account_id' => 'page-story',
            'access_token' => 'page-access-token',
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
}
