<?php

namespace Database\Factories;

use App\Domain\Content\Enums\PostTargetPublishAttemptStatus;
use App\Domain\Content\Models\PostTarget;
use App\Domain\Content\Models\PostTargetPublishAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostTargetPublishAttempt>
 */
class PostTargetPublishAttemptFactory extends Factory
{
    protected $model = PostTargetPublishAttempt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_target_id' => PostTarget::factory(),
            'attempt_number' => 1,
            'status' => PostTargetPublishAttemptStatus::Processing,
            'started_at' => now(),
            'finished_at' => null,
            'error_code' => null,
            'error_message' => null,
            'provider_response' => null,
            'job_uuid' => null,
        ];
    }
}
