<?php

namespace App\Services\SocialPlatforms\Drivers;

use App\Domain\Content\Enums\PostType;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostTarget;
use App\Services\SocialPlatforms\Contracts\SocialPlatformPublisher;
use App\Services\SocialPlatforms\Data\PlatformPublishResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

class FacebookPublisher implements SocialPlatformPublisher
{
    public function publish(Post $post, PostTarget $target): PlatformPublishResponse
    {
        $postType = (string) $post->getRawOriginal('type');
        if ($postType !== PostType::Default->value) {
            return PlatformPublishResponse::failed(
                errorCode: 'UNSUPPORTED_POST_TYPE',
                errorMessage: 'Facebook publisher supports only ['.PostType::Default->value.'] post type.',
                recoverable: false,
                providerResponse: ['type' => $postType],
            );
        }

        try {
            /** @var array<string, mixed> $response */
            $response = $this->httpClient()
                ->post('/'.$target->channel->platform_account_id.'/feed', [
                    'message' => $post->content,
                    'access_token' => $target->channel->access_token,
                ])
                ->throw()
                ->json();

            $externalPostId = $response['id'] ?? null;
            if (! is_string($externalPostId) || $externalPostId === '') {
                return PlatformPublishResponse::failed(
                    errorCode: 'INVALID_GRAPH_RESPONSE',
                    errorMessage: 'Facebook Graph API did not return a publish id.',
                    recoverable: false,
                    providerResponse: $response,
                );
            }

            return PlatformPublishResponse::success(
                externalPostId: $externalPostId,
                providerResponse: $response,
            );
        } catch (ConnectionException $exception) {
            return PlatformPublishResponse::failed(
                errorCode: 'FACEBOOK_CONNECTION_ERROR',
                errorMessage: $exception->getMessage(),
                recoverable: true,
            );
        } catch (RequestException $exception) {
            $statusCode = $exception->response?->status();
            $payload = $exception->response?->json();

            $errorCode = is_int($statusCode)
                ? 'FACEBOOK_HTTP_'.$statusCode
                : 'FACEBOOK_HTTP_ERROR';

            // TODO: Declare HTTP codes
            $recoverable = $statusCode === 401 || $statusCode === 429 || $statusCode === 503 || ($statusCode !== null && $statusCode >= 500);

            return PlatformPublishResponse::failed(
                errorCode: $errorCode,
                errorMessage: $exception->getMessage(),
                recoverable: $recoverable,
                providerResponse: is_array($payload) ? $payload : null,
            );
        } catch (Throwable $exception) {
            return PlatformPublishResponse::failed(
                errorCode: 'FACEBOOK_PUBLISH_ERROR',
                errorMessage: $exception->getMessage(),
                recoverable: false,
            );
        }
    }

    private function httpClient(): PendingRequest
    {
        $url = sprintf(
            'https://graph.facebook.com/%s',
            (string) config('services.facebook.graph_version', 'v25.0'),
        );

        // TODO: Define HTTP client
        return Http::baseUrl($url)
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->retry([200, 500, 1000]);
    }
}
