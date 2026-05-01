<?php

namespace App\Services\SocialPlatforms\Drivers;

use App\Enums\Platform;
use App\Services\SocialPlatforms\Contracts\SocialPlatformDriver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class InstagramDriver implements SocialPlatformDriver
{
    private const string CHANNEL_DISCOVERY_SCOPE = 'instagram_business_basic,instagram_business_manage_messages,instagram_business_manage_comments,instagram_business_content_publish,instagram_business_manage_insights';

    public function buildAuthorizationUrl(string $state): string
    {
        $baseUrl = 'https://www.instagram.com/oauth/authorize';

        $query = http_build_query([
            'client_id' => config('services.instagram.app_id'),
            'redirect_uri' => urldecode((string) config('services.instagram.redirect_uri')),
            'response_type' => 'code',
            'scope' => self::CHANNEL_DISCOVERY_SCOPE,
            'state' => $state,
        ]);

        return $baseUrl.'?'.$query;
    }

    public function handleCallback(string $authorizationCode): array
    {
        /** @var array<string, mixed> $shortLivedTokenResponse */
        $shortLivedTokenResponse = Http::asForm()
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->retry([200, 500, 1000])
            ->post('https://api.instagram.com/oauth/access_token', [
                'grant_type' => 'authorization_code',
                'client_id' => config('services.instagram.app_id'),
                'client_secret' => config('services.instagram.app_secret'),
                'redirect_uri' => config('services.instagram.redirect_uri'),
                'code' => $authorizationCode,
            ])
            ->throw()
            ->json();

        $shortLivedToken = $this->stringValue($shortLivedTokenResponse, 'access_token');
        $userId = $this->stringValue($shortLivedTokenResponse, 'user_id');

        /** @var array<string, mixed> $longLivedTokenResponse */
        $longLivedTokenResponse = Http::asForm()
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->retry([200, 500, 1000])
            ->get('https://graph.instagram.com/access_token', [
                'grant_type' => 'ig_exchange_token',
                'client_secret' => config('services.instagram.app_secret'),
                'access_token' => $shortLivedToken,
            ])
            ->throw()
            ->json();

        $longLivedToken = $this->stringValue($longLivedTokenResponse, 'access_token');

        return [
            'provider_user_id' => $userId,
            'access_token' => $longLivedToken,
            'expires_at' => $this->resolveExpiry($longLivedTokenResponse),
        ];
    }

    public function discoverChannels(string $accessToken): array
    {
        /** @var array<string, mixed> $profile */
        $profile = $this->graphClient()
            ->get('/me', [
                'fields' => 'id,username,account_type',
                'access_token' => $accessToken,
            ])
            ->throw()
            ->json();

        $id = $this->stringValue($profile, 'id');
        $username = $this->stringValue($profile, 'username', required: false) ?? $id;

        return [[
            'platform_slug' => Platform::INSTAGRAM->value,
            'platform_account_id' => $id,
            'handle' => $username,
            'access_token' => null,
        ]];
    }

    private function graphClient(): PendingRequest
    {
        return Http::baseUrl('https://graph.instagram.com')
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->retry([200, 500, 1000]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function stringValue(array $payload, string $key, bool $required = true): ?string
    {
        $value = $payload[$key] ?? null;

        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (! $required) {
            return null;
        }

        throw new RuntimeException("Instagram API payload is missing required [$key] field.");
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveExpiry(array $payload): ?CarbonImmutable
    {
        $expiresIn = $payload['expires_in'] ?? null;

        if (! is_int($expiresIn) || $expiresIn <= 0) {
            return null;
        }

        return CarbonImmutable::now()->addSeconds($expiresIn);
    }
}
