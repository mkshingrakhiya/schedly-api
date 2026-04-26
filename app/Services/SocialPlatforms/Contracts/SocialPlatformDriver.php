<?php

namespace App\Services\SocialPlatforms\Contracts;

use Carbon\CarbonImmutable;

interface SocialPlatformDriver
{
    public function buildAuthorizationUrl(string $state): string;

    /**
     * @return array{provider_user_id: string, access_token: string, expires_at: CarbonImmutable|null}
     */
    public function handleCallback(string $authorizationCode): array;

    /**
     * @return list<array{platform_slug: string, platform_account_id: string, handle: string, access_token: string|null}>
     */
    public function discoverChannels(string $accessToken): array;
}
