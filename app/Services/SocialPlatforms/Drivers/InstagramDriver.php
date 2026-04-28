<?php

namespace App\Services\SocialPlatforms\Drivers;

use App\Services\SocialPlatforms\Contracts\SocialPlatformDriver;
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
        $payload = Http::asForm()
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->retry([200, 500, 1000])
            ->post('https://api.instagram.com/oauth/access_token', [
                'client_id' => config('services.instagram.app_id'),
                'client_secret' => config('services.instagram.app_secret'),
                'grant_type' => 'authorization_code',
                'redirect_uri' => config('services.instagram.redirect_uri'),
                'code' => $authorizationCode,
            ])
            ->throw()
            ->json();

        if (! is_array($payload)) {
            throw new RuntimeException('Instagram OAuth token response is not a JSON object.');
        }

        return [
            'provider_user_id' => $this->stringValue($payload, 'user_id'),
            'access_token' => $this->stringValue($payload, 'access_token'),
            'expires_at' => null,
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
            'platform_slug' => 'instagram',
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
}
