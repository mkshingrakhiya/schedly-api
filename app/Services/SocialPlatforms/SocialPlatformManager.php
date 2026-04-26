<?php

namespace App\Services\SocialPlatforms;

use App\Services\SocialPlatforms\Contracts\SocialPlatformDriver;
use App\Services\SocialPlatforms\Drivers\FacebookDriver;
use InvalidArgumentException;

class SocialPlatformManager
{
    public function __construct(private FacebookDriver $facebookDriver) {}

    public function driver(string $platformSlug): SocialPlatformDriver
    {
        return match ($platformSlug) {
            'facebook' => $this->facebookDriver,
            default => throw new InvalidArgumentException("Unsupported social platform driver [$platformSlug]."),
        };
    }
}
