<?php

namespace App\Services\SocialPlatforms;

use App\Domain\Content\Models\Channel;
use App\Domain\Content\Models\PlatformOAuthConnection;
use App\Domain\Content\Models\PlatformOAuthConnectionState;
use App\Domain\Content\Services\ChannelService as WorkspaceChannelService;
use App\Models\Platform;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class FacebookOAuthService
{
    public function __construct(
        private SocialPlatformManager $socialPlatformManager,
        private WorkspaceChannelService $channelService,
    ) {}

    /**
     * @return array{authorizationUrl: string, expiresAt: string}
     */
    public function buildConnectionPayload(Workspace $workspace, User $user): array
    {
        $platform = Platform::findBySlug('facebook');

        $state = PlatformOAuthConnectionState::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'platform_id' => $platform->id,
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
        ]);

        $authorizationUrl = $this->socialPlatformManager
            ->driver('facebook')
            ->buildAuthorizationUrl($state->uuid);

        return [
            'authorizationUrl' => $authorizationUrl,
            'expiresAt' => $state->expires_at->toISOString(),
        ];
    }

    /**
     * @return list<array{platform_slug: string, platform_account_id: string, handle: string}>
     */
    public function handleCallback(string $stateUuid, string $authorizationCode): array
    {
        $state = PlatformOAuthConnectionState::findByUuid($stateUuid);

        if ($state === null) {
            throw ValidationException::withMessages([
                'state' => ['Invalid OAuth state.'],
            ]);
        }

        if ($state->expires_at->isPast()) {
            $state->delete();

            throw ValidationException::withMessages([
                'state' => ['OAuth state has expired.'],
            ]);
        }

        $state->load('workspace', 'user');

        $workspace = $state->workspace;
        $user = $state->user;

        $state->delete();

        $platform = Platform::findBySlug('facebook');

        $driver = $this->socialPlatformManager->driver('facebook');
        $callbackPayload = $driver->handleCallback($authorizationCode);

        PlatformOAuthConnection::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'platform_id' => $platform->id,
                'provider_user_id' => $callbackPayload['provider_user_id'],
            ],
            [
                'access_token' => $callbackPayload['access_token'],
                'expires_at' => $callbackPayload['expires_at'],
                'created_by' => $user->id,
            ],
        );

        $channels = $driver->discoverChannels($callbackPayload['access_token']);

        return $this->withoutSensitiveFields($channels);
    }

    /**
     * @param  list<array{platform_account_id: string, handle?: string|null}>  $selectedChannels
     * @return Collection<int, Channel>
     */
    public function storeSelectedChannels(Workspace $workspace, User $user, array $selectedChannels): Collection
    {
        $facebookPlatform = Platform::findBySlug('facebook');

        $connection = PlatformOAuthConnection::query()
            ->where('workspace_id', $workspace->id)
            ->where('platform_id', $facebookPlatform->id)
            ->latest('id')
            ->first();

        if ($connection === null) {
            throw ValidationException::withMessages([
                'channels' => ['No Facebook OAuth connection found for this workspace.'],
            ]);
        }

        $discoverableChannels = collect(
            $this->socialPlatformManager->driver('facebook')->discoverChannels($connection->access_token),
        )->keyBy(fn (array $channel): string => $this->candidateKey($channel['platform_slug'], $channel['platform_account_id']));

        $createdChannels = new Collection;

        foreach ($selectedChannels as $index => $selection) {
            $selectionKey = $this->candidateKey('facebook', $selection['platform_account_id']);
            $candidate = $discoverableChannels->get($selectionKey);

            if (! is_array($candidate)) {
                throw ValidationException::withMessages([
                    "channels.$index.platform_account_id" => ['Channel is not available for the current Facebook connection.'],
                ]);
            }

            $handle = $selection['handle'] ?? null;
            if (! is_string($handle) || $handle === '') {
                $handle = $candidate['handle'];
            }

            $existing = Channel::query()
                ->where('workspace_id', $workspace->id)
                ->where('platform_id', $facebookPlatform->id)
                ->where('platform_account_id', $selection['platform_account_id'])
                ->first();

            if ($existing !== null) {
                throw ValidationException::withMessages([
                    "channels.$index.platform_account_id" => ['This channel has already been connected.'],
                ]);
            }

            $pageToken = $candidate['access_token'] ?? null;
            $accessToken = is_string($pageToken) && $pageToken !== '' ? $pageToken : $connection->access_token;

            $createdChannels->push(
                $this->channelService->create($workspace, $user, [
                    'platform_id' => $facebookPlatform->id,
                    'handle' => $handle,
                    'platform_account_id' => $selection['platform_account_id'],
                    'access_token' => $accessToken,
                    'refresh_token' => null,
                    'token_expires_at' => $connection->expires_at,
                ]),
            );
        }

        return $createdChannels->load('platform');
    }

    private function candidateKey(string $platformSlug, string $platformAccountId): string
    {
        return $platformSlug.':'.$platformAccountId;
    }

    /**
     * @param  list<array{platform_slug: string, platform_account_id: string, handle: string, access_token: string|null}>  $channels
     * @return list<array{platform_slug: string, platform_account_id: string, handle: string}>
     */
    private function withoutSensitiveFields(array $channels): array
    {
        return array_map(
            fn (array $channel): array => [
                'platform_slug' => $channel['platform_slug'],
                'platform_account_id' => $channel['platform_account_id'],
                'handle' => $channel['handle'],
            ],
            $channels,
        );
    }
}
