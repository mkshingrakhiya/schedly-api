<?php

namespace App\Services\SocialPlatforms;

use App\Enums\Platform;
use App\Services\SocialPlatforms\Contracts\SocialPlatformPublisher;
use App\Services\SocialPlatforms\Drivers\FacebookPublisher;
use App\Services\SocialPlatforms\Drivers\InstagramPublisher;
use InvalidArgumentException;

class PlatformPublishManager
{
    public function __construct(
        private FacebookPublisher $facebookPublisher,
        private InstagramPublisher $instagramPublisher,
    ) {}

    public function publisher(string $platformSlug): SocialPlatformPublisher
    {
        return match ($platformSlug) {
            Platform::FACEBOOK->value => $this->facebookPublisher,
            Platform::INSTAGRAM->value => $this->instagramPublisher,
            default => throw new InvalidArgumentException("Unsupported social platform publisher [$platformSlug]."),
        };
    }
}
