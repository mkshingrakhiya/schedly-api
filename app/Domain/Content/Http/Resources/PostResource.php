<?php

namespace App\Domain\Content\Http\Resources;

use App\Domain\Auth\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Domain\Content\Models\Post
 */
class PostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'content' => $this->content,
            'status' => $this->status->value,
            'createdBy' => UserResource::make($this->whenLoaded('creator')),
            'targets' => PostTargetResource::collection($this->whenLoaded('targets')),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
