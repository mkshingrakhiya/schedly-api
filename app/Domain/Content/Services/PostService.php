<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Enums\PostStatus;
use App\Domain\Content\Enums\PostTargetStatus;
use App\Domain\Content\Models\Channel;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostTarget;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PostService
{
    public function __construct(private PostMediaService $postMediaService) {}

    public function index(Workspace $workspace, int $perPage = 15): LengthAwarePaginator
    {
        return Post::query()
            ->where('workspace_id', $workspace->id)
            ->with(['creator', 'targets.channel', 'media.owner'])
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Workspace $workspace, User $creator, array $attributes): Post
    {
        return DB::transaction(function () use ($workspace, $creator, $attributes): Post {
            $post = Post::query()->create([
                'workspace_id' => $workspace->id,
                'created_by' => $creator->id,
                'content' => $attributes['content'],
                'status' => $attributes['status'] ?? PostStatus::Scheduled,
            ]);

            if (! empty($attributes['targets'])) {
                $this->replaceTargets($post, $workspace, $attributes['targets']);
            }

            if (array_key_exists('media_uuids', $attributes)) {
                $this->postMediaService->link($workspace, $post, $attributes['media_uuids']);
            }

            return $post->fresh()->load(['creator', 'targets.channel', 'media.owner']);
        });
    }

    public function get(Workspace $workspace, Post $post): Post
    {
        return Post::query()
            ->where('workspace_id', $workspace->id)
            ->where('id', $post->id)
            ->with(['creator', 'targets.channel', 'media.owner'])
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Post $post, Workspace $workspace, array $attributes): Post
    {
        return DB::transaction(function () use ($post, $workspace, $attributes): Post {
            if (array_key_exists('content', $attributes)) {
                $post->content = $attributes['content'];
            }

            if (array_key_exists('status', $attributes)) {
                $post->status = $attributes['status'];
            }

            $post->save();

            if (array_key_exists('targets', $attributes) && ! empty($attributes['targets'])) {
                $this->replaceTargets($post, $workspace, $attributes['targets']);
            }

            if (array_key_exists('media_uuids', $attributes)) {
                $this->postMediaService->sync($post, $workspace, $attributes['media_uuids']);
            }

            return $post->fresh()->load(['creator', 'targets.channel', 'media.owner']);
        });
    }

    public function delete(Post $post, Workspace $workspace): void
    {
        DB::transaction(function () use ($post): void {
            $post->load('media');

            foreach ($post->media as $media) {
                $this->postMediaService->delete($media);
            }

            $post->delete();
        });
    }

    /**
     * @param  list<array{channel_uuid: string, scheduled_at: string, platform_options?: array<string, mixed>|null}>  $targets
     */
    private function replaceTargets(Post $post, Workspace $workspace, array $targets): void
    {
        $post->targets()->delete();

        foreach ($targets as $target) {
            $channelUuid = Arr::get($target, 'channel_uuid');
            $scheduledAt = Arr::get($target, 'scheduled_at');

            if (! is_string($channelUuid) || ! is_string($scheduledAt)) {
                throw ValidationException::withMessages([
                    "targets.$channelUuid" => ['Each target must include a valid channel_uuid and scheduled_at.'],
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
