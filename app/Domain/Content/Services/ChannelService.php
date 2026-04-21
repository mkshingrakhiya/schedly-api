<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Models\Channel;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ChannelService
{
    public function index(Workspace $workspace, int $perPage = 15): LengthAwarePaginator
    {
        return Channel::query()
            ->where('workspace_id', $workspace->id)
            ->with('platform')
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * @param  array{platform_id: int, handle: string, platform_account_id: string, access_token: string, refresh_token?: string|null, token_expires_at?: \Illuminate\Support\Carbon|string|null}  $attributes
     */
    public function create(Workspace $workspace, User $creator, array $attributes): Channel
    {
        return Channel::query()->create([
            'workspace_id' => $workspace->id,
            'platform_id' => $attributes['platform_id'],
            'handle' => $attributes['handle'],
            'platform_account_id' => $attributes['platform_account_id'],
            'access_token' => $attributes['access_token'],
            'refresh_token' => $attributes['refresh_token'] ?? null,
            'token_expires_at' => $attributes['token_expires_at'] ?? null,
            'created_by' => $creator->id,
        ]);
    }

    public function delete(Workspace $workspace, Channel $channel): void
    {
        // TODO: Review this check
        if ($channel->workspace_id !== $workspace->id) {
            abort(404);
        }

        $channel->delete();
    }
}
