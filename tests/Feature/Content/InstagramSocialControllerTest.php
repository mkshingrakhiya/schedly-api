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

class InstagramSocialControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_access_instagram_social_endpoints(): void
    {
        $workspace = Workspace::factory()->create();
        $headers = $this->workspaceHeader($workspace->uuid);

        $this->getJson('/api/v1/social/instagram/connect', $headers)->assertUnauthorized();
        $this->postJson('/api/v1/social/instagram/channels', ['channels' => []], $headers)->assertUnauthorized();
    }

    public function test_instagram_social_endpoints_require_workspace_header(): void
    {
        [$_workspace, $owner] = $this->workspaceAndOwner();
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/social/instagram/connect')->assertStatus(400);

        $this->postJson('/api/v1/social/instagram/channels', ['channels' => []])->assertStatus(400);
    }

    public function test_non_member_cannot_connect_instagram_account(): void
    {
        [$workspace] = $this->workspaceAndOwner();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/social/instagram/connect', $this->workspaceHeader($workspace->uuid))
            ->assertForbidden();
    }

    public function test_owner_can_start_instagram_oauth_flow(): void
    {
        [$workspace, $owner] = $this->workspaceAndOwner();
        Sanctum::actingAs($owner);

        $response = $this
            ->getJson('/api/v1/social/instagram/connect', $this->workspaceHeader($workspace->uuid))
            ->assertOk()
            ->assertJsonStructure(['data' => ['authorizationUrl', 'expiresAt']]);

        $authorizationUrl = (string) $response->json('data.authorizationUrl');
        $this->assertStringContainsString('instagram.com', $authorizationUrl);

        $query = parse_url($authorizationUrl, PHP_URL_QUERY);
        parse_str(is_string($query) ? $query : '', $params);
        $stateUuid = (string) ($params['state'] ?? '');

        $this->assertNotSame('', $stateUuid);

        $instagramPlatform = Platform::query()->where('slug', 'instagram')->firstOrFail();

        $this->assertDatabaseHas('platform_oauth_connection_states', [
            'uuid' => $stateUuid,
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'platform_id' => $instagramPlatform->id,
        ]);
    }

    public function test_callback_rejects_unknown_state(): void
    {
        [$workspace, $owner] = $this->workspaceAndOwner();
        Sanctum::actingAs($owner);

        $this
            ->getJson(
                '/api/v1/social/instagram/callback?code=test-code&state='.fake()->uuid(),
                $this->workspaceHeader($workspace->uuid),
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['state']);
    }

    public function test_callback_rejects_expired_state(): void
    {
        [$workspace, $owner] = $this->workspaceAndOwner();
        $instagramPlatform = Platform::query()->where('slug', 'instagram')->firstOrFail();
        $state = PlatformOAuthConnectionState::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'platform_id' => $instagramPlatform->id,
            'expires_at' => now()->subMinute(),
            'created_at' => now()->subMinutes(11),
        ]);

        Sanctum::actingAs($owner);

        $this
            ->getJson(
                '/api/v1/social/instagram/callback?code=test-code&state='.$state->uuid,
                $this->workspaceHeader($workspace->uuid),
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['state']);

        $this->assertDatabaseMissing('platform_oauth_connection_states', ['id' => $state->id]);
    }

    public function test_callback_persists_oauth_connection_and_returns_discovered_channels(): void
    {
        [$workspace, $owner] = $this->workspaceAndOwner();
        $instagramPlatform = Platform::query()->where('slug', 'instagram')->firstOrFail();

        $state = PlatformOAuthConnectionState::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'platform_id' => $instagramPlatform->id,
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
        ]);

        Sanctum::actingAs($owner);
        Http::fake(function (Request $request) {
            $url = $request->url();
            $data = $request->data();

            if (str_contains($url, 'api.instagram.com/oauth/access_token')) {
                return Http::response(['access_token' => 'ig-short-token', 'user_id' => 'ig-user-1'], 200);
            }

            if (str_contains($url, 'graph.instagram.com/access_token') && ($data['grant_type'] ?? null) === 'ig_exchange_token') {
                return Http::response([
                    'access_token' => 'ig-long-token',
                    'token_type' => 'bearer',
                    'expires_in' => 5183944,
                ], 200);
            }

            if (str_contains($url, 'graph.instagram.com/me')) {
                return Http::response([
                    'id' => 'ig-user-1',
                    'username' => 'mycafe',
                    'account_type' => 'BUSINESS',
                ], 200);
            }

            return Http::response([], 500);
        });

        $this
            ->getJson(
                '/api/v1/social/instagram/callback?code=test-code&state='.$state->uuid,
                $this->workspaceHeader($workspace->uuid),
            )
            ->assertOk()
            ->assertJsonPath('data.channels.0.platform_slug', 'instagram')
            ->assertJsonPath('data.channels.0.platform_account_id', 'ig-user-1')
            ->assertJsonPath('data.channels.0.handle', 'mycafe')
            ->assertJsonMissingPath('data.channels.0.access_token');

        $connection = PlatformOAuthConnection::query()
            ->where('workspace_id', $workspace->id)
            ->where('platform_id', $instagramPlatform->id)
            ->firstOrFail();

        $this->assertSame('ig-user-1', $connection->provider_user_id);
        $this->assertSame('ig-long-token', $connection->access_token);
        $this->assertNotSame('ig-long-token', (string) $connection->getRawOriginal('access_token'));
        $this->assertNotNull($connection->expires_at);
        $this->assertDatabaseMissing('platform_oauth_connection_states', ['id' => $state->id]);
    }

    public function test_owner_can_store_selected_instagram_channels(): void
    {
        [$workspace, $owner] = $this->workspaceAndOwner();
        $instagramPlatform = Platform::query()->where('slug', 'instagram')->firstOrFail();
        PlatformOAuthConnection::factory()->create([
            'workspace_id' => $workspace->id,
            'platform_id' => $instagramPlatform->id,
            'provider_user_id' => 'ig-user-1',
            'access_token' => 'workspace-instagram-token',
            'created_by' => $owner->id,
        ]);

        Sanctum::actingAs($owner);
        Http::fake(function (Request $request) {
            $url = $request->url();

            if (str_contains($url, 'graph.instagram.com/me')) {
                return Http::response([
                    'id' => 'ig-user-1',
                    'username' => 'mycafe',
                    'account_type' => 'BUSINESS',
                ], 200);
            }

            return Http::response([], 500);
        });

        $payload = [
            'channels' => [
                [
                    'platform_slug' => 'instagram',
                    'platform_account_id' => 'ig-user-1',
                    'handle' => 'mycafe',
                ],
            ],
        ];

        $this
            ->postJson('/api/v1/social/instagram/channels', $payload, $this->workspaceHeader($workspace->uuid))
            ->assertCreated()
            ->assertJsonPath('data.0.platform.slug', 'instagram')
            ->assertJsonMissingPath('data.0.access_token');

        $this->assertDatabaseHas('channels', [
            'workspace_id' => $workspace->id,
            'platform_id' => $instagramPlatform->id,
            'platform_account_id' => 'ig-user-1',
            'handle' => 'mycafe',
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
