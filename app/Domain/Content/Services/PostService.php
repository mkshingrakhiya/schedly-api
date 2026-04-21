<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Enums\PostStatus;
use App\Domain\Content\Enums\PostTargetStatus;
use App\Models\Channel;
use App\Models\Post;
use App\Models\PostTarget;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PostService
{
    public function get(Workspace $workspace, string $postUuid): Post
    {
        return Post::query()
            ->where('uuid', $postUuid)
            ->where('workspace_id', $workspace->id)
            ->with(['postTargets.channel.platform'])
            ->firstOrFail();
    }

    /**
     * @return LengthAwarePaginator<int, Post>
     */
    public function paginateForWorkspace(Workspace $workspace, int $perPage = 15): LengthAwarePaginator
    {
        return Post::query()
            ->where('workspace_id', $workspace->id)
            ->with(['postTargets.channel.platform'])
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * @param  array{content: string, status: string, targets: list<array{channel_uuid: string, scheduled_at: string, platform_options?: array<string, mixed>|null}>}  $data
     */
    public function create(Workspace $workspace, User $user, array $data): Post
    {
        return DB::transaction(function () use ($workspace, $user, $data): Post {
            $post = Post::query()->create([
                'workspace_id' => $workspace->id,
                'created_by' => $user->id,
                'content' => $data['content'],
                'status' => $data['status'],
            ]);

            $this->replaceTargets($post, $workspace, $data['targets']);

            return $post->fresh()->load(['postTargets.channel.platform']);
        });
    }

    /**
     * @param  array{content?: string, status?: string, targets?: list<array{channel_uuid: string, scheduled_at: string, platform_options?: array<string, mixed>|null}>}  $data
     */
    public function update(Post $post, Workspace $workspace, array $data): Post
    {
        return DB::transaction(function () use ($post, $workspace, $data): Post {
            if (array_key_exists('content', $data)) {
                $post->content = $data['content'];
            }

            if (array_key_exists('status', $data)) {
                $post->status = PostStatus::from($data['status']);
            }

            $post->save();

            if (array_key_exists('targets', $data) && is_array($data['targets'])) {
                $this->replaceTargets($post, $workspace, $data['targets']);
            }

            return $post->fresh()->load(['postTargets.channel.platform']);
        });
    }

    public function delete(Post $post): void
    {
        $post->delete();
    }

    /**
     * @param  list<array{channel_uuid: string, scheduled_at: string, platform_options?: array<string, mixed>|null}>  $targets
     */
    private function replaceTargets(Post $post, Workspace $workspace, array $targets): void
    {
        $post->postTargets()->delete();

        foreach ($targets as $target) {
            $channelUuid = Arr::get($target, 'channel_uuid');
            $scheduledAt = Arr::get($target, 'scheduled_at');

            if (! is_string($channelUuid) || ! is_string($scheduledAt)) {
                throw ValidationException::withMessages([
                    'targets' => ['Each target must include channel_uuid and scheduled_at.'],
                ]);
            }

            $channel = Channel::query()
                ->where('uuid', $channelUuid)
                ->where('workspace_id', $workspace->id)
                ->first();

            if ($channel === null) {
                throw ValidationException::withMessages([
                    'targets' => ['One or more channels are invalid for this workspace.'],
                ]);
            }

            PostTarget::query()->create([
                'post_id' => $post->id,
                'channel_id' => $channel->id,
                'status' => PostTargetStatus::Pending,
                'scheduled_at' => $scheduledAt,
                'platform_options' => Arr::get($target, 'platform_options'),
            ]);
        }
    }
}
