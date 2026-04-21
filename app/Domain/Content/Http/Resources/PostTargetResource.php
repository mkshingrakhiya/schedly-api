<?php

namespace App\Domain\Content\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Domain\Content\Models\PostTarget
 */
class PostTargetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'scheduledAt' => $this->scheduled_at?->toISOString(),
            'publishedAt' => $this->published_at?->toISOString(),
            'platformOptions' => $this->platform_options,
            'channel' => [
                'uuid' => $this->channel->uuid,
                'handle' => $this->channel->handle,
            ],
        ];
    }
}
