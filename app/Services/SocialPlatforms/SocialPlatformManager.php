<?php

namespace App\Services\SocialPlatforms;

use App\Enums\Platform;
use App\Services\SocialPlatforms\Contracts\SocialPlatformDriver;
use App\Services\SocialPlatforms\Drivers\FacebookDriver;
use App\Services\SocialPlatforms\Drivers\InstagramDriver;
use InvalidArgumentException;

class SocialPlatformManager
{
    public function __construct(
        private FacebookDriver $facebookDriver,
        private InstagramDriver $instagramDriver,
    ) {}

    public function driver(string $platformSlug): SocialPlatformDriver
    {
        return match ($platformSlug) {
            Platform::FACEBOOK->value => $this->facebookDriver,
            Platform::INSTAGRAM->value => $this->instagramDriver,
            default => throw new InvalidArgumentException("Unsupported social platform driver [$platformSlug]."),
        };
    }
}
