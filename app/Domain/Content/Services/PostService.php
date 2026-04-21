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
use Illuminate\Support\Facades\DB;

class PostService
{
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

            return $this->loadPostRelations($post->fresh());
        });
    }

    public function paginateForWorkspace(Workspace $workspace): LengthAwarePaginator
    {
        return Post::query()
            ->where('workspace_id', $workspace->id)
            ->with(['creator', 'targets.channel'])
            ->latest('id')
            ->paginate();
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

            if (array_key_exists('targets', $attributes)) {
                $post->targets()->delete();
                if (! empty($attributes['targets'])) {
                    $this->replaceTargets($post, $workspace, $attributes['targets']);
                }
            }

            return $this->loadPostRelations($post->fresh());
        });
    }

    public function delete(Post $post): void
    {
        $post->delete();
    }

    /**
     * @param  list<array<string, mixed>>  $targets
     */
    private function replaceTargets(Post $post, Workspace $workspace, array $targets): void
    {
        foreach ($targets as $targetInput) {
            $channel = Channel::query()
                ->where('uuid', $targetInput['channelUuid'])
                ->where('workspace_id', $workspace->id)
                ->firstOrFail();

            PostTarget::query()->create([
                'post_id' => $post->id,
                'channel_id' => $channel->id,
                'status' => PostTargetStatus::Pending,
                'scheduled_at' => $targetInput['scheduledAt'],
                'published_at' => $targetInput['publishedAt'] ?? null,
                'platform_options' => $targetInput['platformOptions'] ?? null,
            ]);
        }
    }

    private function loadPostRelations(Post $post): Post
    {
        return $post->load(['creator', 'targets.channel']);
    }
}
