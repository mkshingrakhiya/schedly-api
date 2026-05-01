<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Enums\PostStatus;
use App\Domain\Content\Enums\PostTargetPublishAttemptStatus;
use App\Domain\Content\Enums\PostTargetStatus;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostTarget;
use App\Domain\Content\Models\PostTargetPublishAttempt;
use App\Services\SocialPlatforms\Data\PlatformPublishResponse;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PostTargetPublishingService
{
    public function beginAttempt(PostTarget $target, ?string $jobUuid): PostTargetPublishAttempt
    {
        return DB::transaction(function () use ($target, $jobUuid): PostTargetPublishAttempt {
            /** @var PostTarget $lockedTarget */
            $lockedTarget = PostTarget::query()
                ->lockForUpdate()
                ->findOrFail($target->id);

            $attemptNumber = $lockedTarget->attempt_count + 1;
            $now = CarbonImmutable::now();

            $lockedTarget->update([
                'status' => PostTargetStatus::Processing,
                'attempt_count' => $attemptNumber,
                'last_attempt_at' => $now,
            ]);

            return PostTargetPublishAttempt::query()->create([
                'post_target_id' => $lockedTarget->id,
                'attempt_number' => $attemptNumber,
                'status' => PostTargetPublishAttemptStatus::Processing,
                'started_at' => $now,
                'job_uuid' => $jobUuid,
            ]);
        });
    }

    public function completeAttempt(PostTarget $target, PostTargetPublishAttempt $attempt, PlatformPublishResponse $result): void
    {
        DB::transaction(function () use ($target, $attempt, $result): void {
            $now = CarbonImmutable::now();

            /** @var PostTarget $lockedTarget */
            $lockedTarget = PostTarget::query()
                ->with('post.targets')
                ->lockForUpdate()
                ->findOrFail($target->id);

            /** @var PostTargetPublishAttempt $lockedAttempt */
            $lockedAttempt = PostTargetPublishAttempt::query()
                ->lockForUpdate()
                ->findOrFail($attempt->id);

            $lockedAttempt->update([
                'status' => $result->successful
                    ? PostTargetPublishAttemptStatus::Completed
                    : PostTargetPublishAttemptStatus::Failed,
                'finished_at' => $now,
                'error_code' => $result->errorCode,
                'error_message' => $result->errorMessage,
                'provider_response' => $result->providerResponse,
            ]);

            $lockedTarget->update([
                'status' => $result->successful ? PostTargetStatus::Completed : PostTargetStatus::Failed,
                'published_at' => $result->successful ? $now : null,
                'external_post_id' => $result->externalPostId,
            ]);

            $this->syncPostStatus($lockedTarget->post);
        });
    }

    public function syncPostStatus(Post $post): void
    {
        $statuses = $post->targets()
            ->pluck('status')
            ->map(function (mixed $status): ?string {
                if ($status instanceof PostTargetStatus) {
                    return $status->value;
                }

                if (is_string($status)) {
                    return $status;
                }

                return null;
            })
            ->filter(fn (?string $status): bool => $status !== null)
            ->all();

        if ($statuses === []) {
            return;
        }

        $hasCompleted = in_array(PostTargetStatus::Completed->value, $statuses, true);
        $hasFailed = in_array(PostTargetStatus::Failed->value, $statuses, true);

        if ($hasCompleted && ! $hasFailed) {
            $post->update(['status' => PostStatus::Published]);

            return;
        }

        if (! $hasCompleted && $hasFailed) {
            $post->update(['status' => PostStatus::Failed]);

            return;
        }

        if ($hasCompleted && $hasFailed) {
            $post->update(['status' => PostStatus::PartiallyPublished]);
        }
    }
}
