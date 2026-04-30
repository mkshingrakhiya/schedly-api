<?php

namespace App\Services\SocialPlatforms\Data;

/**
 * @phpstan-type PublishMetadata array<string, mixed>
 */
class PlatformPublishResponse
{
    /**
     * @param  PublishMetadata|null  $providerResponse
     */
    public function __construct(
        public readonly bool $successful,
        public readonly ?string $externalPostId = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly bool $recoverable = false,
        public readonly ?array $providerResponse = null,
    ) {}

    /**
     * @param  PublishMetadata|null  $providerResponse
     */
    public static function success(?string $externalPostId, ?array $providerResponse = null): self
    {
        return new self(
            successful: true,
            externalPostId: $externalPostId,
            providerResponse: $providerResponse,
        );
    }

    /**
     * @param  PublishMetadata|null  $providerResponse
     */
    public static function failed(
        ?string $errorCode,
        ?string $errorMessage,
        bool $recoverable = false,
        ?array $providerResponse = null,
    ): self {
        return new self(
            successful: false,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            recoverable: $recoverable,
            providerResponse: $providerResponse,
        );
    }
}
