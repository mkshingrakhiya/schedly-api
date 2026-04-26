<?php

namespace Tests\Feature\Content;

use App\Domain\Content\Models\PlatformOAuthConnection;
use App\Domain\Content\Models\PlatformOAuthConnectionState;
use App\Models\Platform;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FacebookSocialControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_access_facebook_social_endpoints(): void
    {
        $workspace = Workspace::factory()->create();
        $headers = $this->workspaceHeader($workspace->uuid);

        $this->getJson('/api/v1/social/facebook/connect', $headers)->assertUnauthorized();
        $this->postJson('/api/v1/social/facebook/channels', ['channels' => []], $headers)->assertUnauthorized();
    }

    public function test_facebook_social_endpoints_require_workspace_header(): void
    {
        [$_workspace, $owner] = $this->workspaceAndOwner();
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/social/facebook/connect')->assertStatus(400);
        
        $this->postJson('/api/v1/social/facebook/channels', ['channels' => []])->assertStatus(400);
    }

    public function test_non_member_cannot_connect_facebook_account(): void
    {
        [$workspace] = $this->workspaceAndOwner();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/social/facebook/connect', $this->workspaceHeader($workspace->uuid))
            ->assertForbidden();
    }

    public function test_owner_can_start_facebook_oauth_flow(): void
    {
        [$workspace, $owner] = $this->workspaceAndOwner();
        Sanctum::actingAs($owner);

        $response = $this
            ->getJson('/api/v1/social/facebook/connect', $this->workspaceHeader($workspace->uuid))
            ->assertOk()
            ->assertJsonStructure(['data' => ['authorizationUrl', 'expiresAt']]);

        $authorizationUrl = (string) $response->json('data.authorizationUrl');
        $this->assertStringContainsString('facebook.com', $authorizationUrl);

        $query = parse_url($authorizationUrl, PHP_URL_QUERY);
        parse_str(is_string($query) ? $query : '', $params);
        $stateUuid = (string) ($params['state'] ?? '');

        $this->assertNotSame('', $stateUuid);

        $this->assertDatabaseHas('platform_oauth_connection_states', [
            'uuid' => $stateUuid,
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_callback_rejects_unknown_state(): void
    {
        [$workspace, $owner] = $this->workspaceAndOwner();
        Sanctum::actingAs($owner);

        $this
            ->getJson(
                '/api/v1/social/facebook/callback?code=test-code&state='.fake()->uuid(),
                $this->workspaceHeader($workspace->uuid),
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['state']);
    }

    public function test_callback_rejects_expired_state(): void
    {
        [$workspace, $owner] = $this->workspaceAndOwner();
        $facebookPlatform = Platform::query()->where('slug', 'facebook')->firstOrFail();
        $state = PlatformOAuthConnectionState::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'platform_id' => $facebookPlatform->id,
            'expires_at' => now()->subMinute(),
            'created_at' => now()->subMinutes(11),
        ]);

        Sanctum::actingAs($owner);

        $this
            ->getJson(
                '/api/v1/social/facebook/callback?code=test-code&state='.$state->uuid,
                $this->workspaceHeader($workspace->uuid),
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['state']);

        $this->assertDatabaseMissing('platform_oauth_connection_states', ['id' => $state->id]);
    }

    public function test_callback_persists_oauth_connection_and_returns_discovered_channels(): void
    {
        [$workspace, $owner] = $this->workspaceAndOwner();
        $facebookPlatform = Platform::query()->where('slug', 'facebook')->firstOrFail();

        $state = PlatformOAuthConnectionState::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'platform_id' => $facebookPlatform->id,
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
        ]);

        Sanctum::actingAs($owner);
        Http::fake(function (Request $request) {
            $url = $request->url();
            $data = $request->data();

            if (str_contains($url, '/oauth/access_token') && ! array_key_exists('grant_type', $data)) {
                return Http::response(['access_token' => 'short-token'], 200);
            }

            if (str_contains($url, '/oauth/access_token') && ($data['grant_type'] ?? null) === 'fb_exchange_token') {
                return Http::response(['access_token' => 'long-token', 'expires_in' => 5184000], 200);
            }

            if (str_contains($url, '/me') && ($data['fields'] ?? null) === 'id' && ! str_contains($url, '/me/')) {
                return Http::response(['id' => 'fb-user-1'], 200);
            }

            if (str_contains($url, '/me/accounts')) {
                return Http::response([
                    'data' => [
                        [
                            'id' => 'page-1',
                            'name' => 'Schedly Cafe',
                            'access_token' => 'page-token-1',
                        ],
                    ],
                ], 200);
            }

            if (str_contains($url, '/me/businesses')) {
                return Http::response(['data' => []], 200);
            }

            return Http::response([], 500);
        });

        $this
            ->getJson(
                '/api/v1/social/facebook/callback?code=test-code&state='.$state->uuid,
                $this->workspaceHeader($workspace->uuid),
            )
            ->assertOk()
            ->assertJsonPath('data.channels.0.platform_slug', 'facebook')
            ->assertJsonPath('data.channels.0.platform_account_id', 'page-1')
            ->assertJsonMissingPath('data.channels.0.access_token');

        $connection = PlatformOAuthConnection::query()
            ->where('workspace_id', $workspace->id)
            ->where('platform_id', $facebookPlatform->id)
            ->firstOrFail();

        $this->assertSame('fb-user-1', $connection->provider_user_id);
        $this->assertSame('long-token', $connection->access_token);
        $this->assertNotSame('long-token', (string) $connection->getRawOriginal('access_token'));
        $this->assertDatabaseMissing('platform_oauth_connection_states', ['id' => $state->id]);
    }

    public function test_callback_merges_business_owned_pages_with_direct_pages(): void
    {
        [$workspace, $owner] = $this->workspaceAndOwner();
        $facebookPlatform = Platform::query()->where('slug', 'facebook')->firstOrFail();

        $state = PlatformOAuthConnectionState::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'platform_id' => $facebookPlatform->id,
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
        ]);

        Sanctum::actingAs($owner);
        Http::fake(function (Request $request) {
            $url = $request->url();
            $data = $request->data();

            if (str_contains($url, '/oauth/access_token') && ! array_key_exists('grant_type', $data)) {
                return Http::response(['access_token' => 'short-token'], 200);
            }

            if (str_contains($url, '/oauth/access_token') && ($data['grant_type'] ?? null) === 'fb_exchange_token') {
                return Http::response(['access_token' => 'long-token', 'expires_in' => 5184000], 200);
            }

            if (str_contains($url, '/me') && ($data['fields'] ?? null) === 'id' && ! str_contains($url, '/me/')) {
                return Http::response(['id' => 'fb-user-1'], 200);
            }

            if (str_contains($url, '/me/accounts')) {
                return Http::response([
                    'data' => [
                        [
                            'id' => 'page-1',
                            'name' => 'Direct Page',
                            'access_token' => 'page-token-1',
                        ],
                    ],
                ], 200);
            }

            if (str_contains($url, '/me/businesses')) {
                return Http::response(['data' => [['id' => 'biz-1']]], 200);
            }

            if (str_contains($url, '/biz-1/owned_pages')) {
                return Http::response([
                    'data' => [
                        [
                            'id' => 'page-2',
                            'name' => 'Business Page',
                            'access_token' => 'page-token-2',
                        ],
                    ],
                ], 200);
            }

            if (str_contains($url, '/biz-1/client_pages')) {
                return Http::response(['data' => []], 200);
            }

            return Http::response([], 500);
        });

        $response = $this->getJson(
            '/api/v1/social/facebook/callback?code=test-code&state='.$state->uuid,
            $this->workspaceHeader($workspace->uuid),
        );

        $response->assertOk();
        $ids = collect($response->json('data.channels'))->pluck('platform_account_id')->sort()->values()->all();
        $this->assertSame(['page-1', 'page-2'], $ids);
    }

    public function test_owner_can_store_selected_facebook_channels(): void
    {
        [$workspace, $owner] = $this->workspaceAndOwner();
        $facebookPlatform = Platform::query()->where('slug', 'facebook')->firstOrFail();
        PlatformOAuthConnection::factory()->create([
            'workspace_id' => $workspace->id,
            'platform_id' => $facebookPlatform->id,
            'provider_user_id' => 'fb-user-1',
            'access_token' => 'workspace-facebook-token',
            'created_by' => $owner->id,
        ]);

        Sanctum::actingAs($owner);
        Http::fake(function (Request $request) {
            $url = $request->url();

            if (str_contains($url, '/me/accounts')) {
                return Http::response([
                    'data' => [
                        [
                            'id' => 'page-1',
                            'name' => 'Schedly Cafe',
                            'access_token' => 'page-token-1',
                        ],
                    ],
                ], 200);
            }

            if (str_contains($url, '/me/businesses')) {
                return Http::response(['data' => []], 200);
            }

            return Http::response([], 500);
        });

        $payload = [
            'channels' => [
                [
                    'platform_slug' => 'facebook',
                    'platform_account_id' => 'page-1',
                    'handle' => 'Schedly Cafe',
                ],
            ],
        ];

        $this
            ->postJson('/api/v1/social/facebook/channels', $payload, $this->workspaceHeader($workspace->uuid))
            ->assertCreated()
            ->assertJsonPath('data.0.platform.slug', 'facebook')
            ->assertJsonMissingPath('data.0.access_token');

        $this->assertDatabaseHas('channels', [
            'workspace_id' => $workspace->id,
            'platform_id' => $facebookPlatform->id,
            'platform_account_id' => 'page-1',
            'handle' => 'Schedly Cafe',
        ]);
    }

    /**
     * @return array{Workspace, User}
     */
    private function workspaceAndOwner(): array
    {
        $workspace = Workspace::factory()->create();
        $owner = User::query()->findOrFail($workspace->owner_id);

        return [$workspace, $owner];
    }
}
