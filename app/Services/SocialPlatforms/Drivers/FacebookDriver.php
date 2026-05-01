<?php

namespace App\Services\SocialPlatforms\Drivers;

use App\Enums\Platform;
use App\Services\SocialPlatforms\Contracts\SocialPlatformDriver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FacebookDriver implements SocialPlatformDriver
{
    private const CHANNEL_DISCOVERY_SCOPE = 'pages_show_list,pages_read_engagement,pages_manage_posts,pages_manage_metadata,business_management';

    public function buildAuthorizationUrl(string $state): string
    {
        $baseUrl = sprintf(
            'https://www.facebook.com/%s/dialog/oauth',
            (string) config('services.facebook.graph_version', 'v25.0'),
        );

        $query = http_build_query([
            'client_id' => config('services.facebook.app_id'),
            'redirect_uri' => urldecode((string) config('services.facebook.redirect_uri')),
            'response_type' => 'code',
            'scope' => self::CHANNEL_DISCOVERY_SCOPE,
            'state' => $state,
        ]);

        return $baseUrl.'?'.$query;
    }

    public function handleCallback(string $authorizationCode): array
    {
        $shortLivedTokenResponse = $this->httpClient()
            ->get('/oauth/access_token', [
                'client_id' => config('services.facebook.app_id'),
                'client_secret' => config('services.facebook.app_secret'),
                'redirect_uri' => config('services.facebook.redirect_uri'),
                'code' => $authorizationCode,
            ])
            ->throw()
            ->json();

        $shortLivedToken = $this->stringValue($shortLivedTokenResponse, 'access_token');

        $longLivedTokenResponse = $this->httpClient()
            ->get('/oauth/access_token', [
                'grant_type' => 'fb_exchange_token',
                'client_id' => config('services.facebook.app_id'),
                'client_secret' => config('services.facebook.app_secret'),
                'fb_exchange_token' => $shortLivedToken,
            ])
            ->throw()
            ->json();

        $longLivedToken = $this->stringValue($longLivedTokenResponse, 'access_token');

        return [
            'provider_user_id' => $this->providerUserIdFromAccessToken($longLivedToken),
            'access_token' => $longLivedToken,
            'expires_at' => $this->resolveExpiry($longLivedTokenResponse),
        ];
    }

    public function discoverChannels(string $accessToken): array
    {
        $directPages = $this->fetchPaginatedList('/me/accounts', [
            'fields' => 'id,name,access_token',
            'access_token' => $accessToken,
        ]);

        $businessPages = $this->fetchBusinessPages($accessToken);

        $byId = [];
        foreach ($directPages as $page) {
            $id = $this->stringValue($page, 'id');
            $byId[$id] = $page;
        }

        foreach ($businessPages as $page) {
            $id = $this->stringValue($page, 'id');
            if (! isset($byId[$id])) {
                $byId[$id] = $page;
            }
        }

        $normalizedChannels = [];
        foreach ($byId as $page) {
            if (! is_array($page)) {
                continue;
            }

            $pageId = $this->stringValue($page, 'id');
            $pageName = $this->stringValue($page, 'name', required: false) ?? $pageId;
            $pageAccessToken = $this->stringValue($page, 'access_token', required: false) ?? '';

            $normalizedChannels[] = [
                'platform_slug' => Platform::FACEBOOK->value,
                'platform_account_id' => $pageId,
                'handle' => $pageName,
                'access_token' => $pageAccessToken === '' ? null : $pageAccessToken,
            ];
        }

        return $normalizedChannels;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchBusinessPages(string $userAccessToken): array
    {
        $businesses = $this->fetchPaginatedList('/me/businesses', [
            'fields' => 'id',
            'access_token' => $userAccessToken,
        ]);

        $pages = [];
        foreach ($businesses as $business) {
            if (! is_array($business)) {
                continue;
            }

            $businessId = $this->stringValue($business, 'id', required: false);
            if ($businessId === null) {
                continue;
            }

            $owned = $this->fetchPaginatedList('/'.$businessId.'/owned_pages', [
                'fields' => 'id,name,access_token',
                'access_token' => $userAccessToken,
            ]);
            $client = $this->fetchPaginatedList('/'.$businessId.'/client_pages', [
                'fields' => 'id,name,access_token',
                'access_token' => $userAccessToken,
            ]);

            foreach (array_merge($owned, $client) as $page) {
                if (is_array($page)) {
                    $pages[] = $page;
                }
            }
        }

        return $pages;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function fetchPaginatedList(string $path, array $query): array
    {
        $items = [];
        $nextUrl = null;
        $first = true;

        while (true) {
            if ($first) {
                $response = $this->httpClient()->get($path, $query)->throw();
                $first = false;
            } else {
                if ($nextUrl === null) {
                    break;
                }
                $response = $this->pagedHttpClient()->get($nextUrl)->throw();
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                break;
            }

            $data = $payload['data'] ?? [];
            if (is_array($data)) {
                foreach ($data as $row) {
                    if (is_array($row)) {
                        $items[] = $row;
                    }
                }
            }

            $nextUrl = $this->extractNextPagingUrl($payload);
            if ($nextUrl === null) {
                break;
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractNextPagingUrl(array $payload): ?string
    {
        $next = $payload['paging']['next'] ?? null;

        return is_string($next) && $next !== '' ? $next : null;
    }

    private function httpClient(): PendingRequest
    {
        $url = sprintf(
            'https://graph.facebook.com/%s',
            (string) config('services.facebook.graph_version', 'v25.0'),
        );

        return Http::baseUrl($url)
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->retry([200, 500, 1000]);
    }

    private function pagedHttpClient(): PendingRequest
    {
        return Http::acceptJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->retry([200, 500, 1000]);
    }

    private function providerUserIdFromAccessToken(string $accessToken): string
    {
        /** @var array<string, mixed> $providerUserResponse */
        $providerUserResponse = $this->httpClient()
            ->get('/me', [
                'fields' => 'id',
                'access_token' => $accessToken,
            ])
            ->throw()
            ->json();

        return $this->stringValue($providerUserResponse, 'id');
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

        if (! $required) {
            return null;
        }

        throw new RuntimeException("Facebook Graph API payload is missing required [$key] field.");
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
