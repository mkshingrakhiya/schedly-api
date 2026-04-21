<?php

namespace App\Domain\Content\Http\Resources;

use App\Domain\Content\Models\PostTarget;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PostTarget
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
            'platformOptions' => $this->platform_options,
            'channel' => $this->whenLoaded('channel', function () {
                $channel = $this->channel;
                $platform = $channel->relationLoaded('platform') ? $channel->platform : null;

                return [
                    'uuid' => $channel->uuid,
                    'handle' => $channel->handle,
                    'platformSlug' => $platform?->slug,
                ];
            }),
            'scheduledAt' => $this->scheduled_at?->toISOString(),
            'publishedAt' => $this->published_at?->toISOString(),
            'status' => $this->status->value,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
