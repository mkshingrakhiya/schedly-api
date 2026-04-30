<?php

namespace App\Services\SocialPlatforms\Drivers;

use App\Domain\Content\Enums\PostType;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostMedia;
use App\Domain\Content\Models\PostTarget;
use App\Services\SocialPlatforms\Contracts\SocialPlatformPublisher;
use App\Services\SocialPlatforms\Data\PlatformPublishResponse;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class InstagramPublisher implements SocialPlatformPublisher
{
    public function publish(Post $post, PostTarget $target): PlatformPublishResponse
    {
        $postType = (string) $post->getRawOriginal('type');
        if ($postType !== PostType::Default->value) {
            return PlatformPublishResponse::failed(
                errorCode: 'UNSUPPORTED_POST_TYPE',
                errorMessage: 'Instagram publisher supports only ['.PostType::Default->value.'] post type.',
                recoverable: false,
                providerResponse: ['type' => $postType],
            );
        }

        /** @var PostMedia|null $media */
        $media = $post->media()->first();

        if ($media === null) {
            return PlatformPublishResponse::failed(
                errorCode: 'INSTAGRAM_MEDIA_REQUIRED',
                errorMessage: 'Instagram publishing requires at least one media item.',
                recoverable: false,
            );
        }

        if (! str_starts_with($media->mime_type, 'image/')) {
            return PlatformPublishResponse::failed(
                errorCode: 'INSTAGRAM_UNSUPPORTED_MEDIA_TYPE',
                errorMessage: 'Instagram publisher currently supports only image media.',
                recoverable: false,
                providerResponse: ['mime_type' => $media->mime_type],
            );
        }

        /** @var FilesystemAdapter $mediaDisk */
        $mediaDisk = Storage::disk($media->disk);
        $mediaUrl = $mediaDisk->url($media->path);

        try {
            /** @var array<string, mixed> $createResponse */
            $createResponse = $this->httpClient()
                ->post('/'.$target->channel->platform_account_id.'/media', [
                    'image_url' => $mediaUrl,
                    'caption' => $post->content,
                    'access_token' => $target->channel->access_token,
                ])
                ->throw()
                ->json();

            $creationId = $createResponse['id'] ?? null;
            if (! is_string($creationId) || $creationId === '') {
                return PlatformPublishResponse::failed(
                    errorCode: 'INVALID_GRAPH_RESPONSE',
                    errorMessage: 'Instagram Graph API did not return a creation id.',
                    recoverable: false,
                    providerResponse: $createResponse,
                );
            }

            /** @var array<string, mixed> $publishResponse */
            $publishResponse = $this->httpClient()
                ->post('/'.$target->channel->platform_account_id.'/media_publish', [
                    'creation_id' => $creationId,
                    'access_token' => $target->channel->access_token,
                ])
                ->throw()
                ->json();

            $externalPostId = $publishResponse['id'] ?? null;
            if (! is_string($externalPostId) || $externalPostId === '') {
                return PlatformPublishResponse::failed(
                    errorCode: 'INVALID_GRAPH_RESPONSE',
                    errorMessage: 'Instagram Graph API did not return a publish id.',
                    recoverable: false,
                    providerResponse: $publishResponse,
                );
            }

            return PlatformPublishResponse::success(
                externalPostId: $externalPostId,
                providerResponse: [
                    'container' => $createResponse,
                    'publish' => $publishResponse,
                ],
            );
        } catch (ConnectionException $exception) {
            return PlatformPublishResponse::failed(
                errorCode: 'INSTAGRAM_CONNECTION_ERROR',
                errorMessage: $exception->getMessage(),
                recoverable: true,
            );
        } catch (RequestException $exception) {
            $statusCode = $exception->response?->status();
            $payload = $exception->response?->json();
            $graphError = is_array($payload['error'] ?? null) ? $payload['error'] : null;
            if (($graphError['code'] ?? null) === 190) {
                return PlatformPublishResponse::failed(
                    errorCode: 'INSTAGRAM_RECONNECT_REQUIRED',
                    errorMessage: 'Instagram access token is invalid or expired. Please reconnect the Instagram channel.',
                    recoverable: false,
                    providerResponse: is_array($payload) ? $payload : null,
                );
            }

            $errorCode = is_int($statusCode)
                ? 'INSTAGRAM_HTTP_'.$statusCode
                : 'INSTAGRAM_HTTP_ERROR';

            $recoverable = $statusCode === 401 || $statusCode === 429 || $statusCode === 503 || ($statusCode !== null && $statusCode >= 500);

            return PlatformPublishResponse::failed(
                errorCode: $errorCode,
                errorMessage: $exception->getMessage(),
                recoverable: $recoverable,
                providerResponse: is_array($payload) ? $payload : null,
            );
        } catch (Throwable $exception) {
            return PlatformPublishResponse::failed(
                errorCode: 'INSTAGRAM_PUBLISH_ERROR',
                errorMessage: $exception->getMessage(),
                recoverable: false,
            );
        }
    }

    private function httpClient(): PendingRequest
    {
        return Http::baseUrl('https://graph.instagram.com')
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->retry([200, 500, 1000]);
    }
}
