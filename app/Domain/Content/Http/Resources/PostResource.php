<?php

namespace App\Domain\Content\Http\Resources;

use App\Domain\Content\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Post
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
            'type' => $this->type->value,
            'targets' => PostTargetResource::collection($this->whenLoaded('targets')),
            'media' => PostMediaResource::collection($this->whenLoaded('media')),
            'status' => $this->status->value,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'deletedAt' => $this->deleted_at?->toISOString(),
        ];
    }
}
