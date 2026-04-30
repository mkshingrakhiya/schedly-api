<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Enums\PostTargetPublishAttemptStatus;
use App\Models\Concerns\HasUuid;
use Database\Factories\PostTargetPublishAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $post_target_id
 * @property int $attempt_number
 * @property PostTargetPublishAttemptStatus $status
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property string|null $error_code
 * @property string|null $error_message
 * @property array<string, mixed>|null $provider_response
 * @property string|null $job_uuid
 */
#[UseFactory(PostTargetPublishAttemptFactory::class)]
class PostTargetPublishAttempt extends Model
{
    use HasFactory, HasUuid;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'post_target_id',
        'attempt_number',
        'status',
        'started_at',
        'finished_at',
        'error_code',
        'error_message',
        'provider_response',
        'job_uuid',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PostTargetPublishAttemptStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'provider_response' => 'array',
        ];
    }

    /**
     * @return BelongsTo<PostTarget, $this>
     */
    public function postTarget(): BelongsTo
    {
        return $this->belongsTo(PostTarget::class);
    }
}
