<?php

namespace App\Domain\Content\Http\Resources;

use App\Domain\Content\Models\Channel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Channel
 */
class ChannelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $platform = $this->relationLoaded('platform') ? $this->platform : null;

        return [
            'uuid' => $this->uuid,
            'handle' => $this->handle,
            'platform' => $platform === null ? null : [
                'slug' => $platform->slug,
                'name' => $platform->name,
            ],
            'tokenExpiresAt' => $this->token_expires_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
