<?php

namespace App\Domain\Content\Http\Resources;

use App\Domain\Content\Models\PostMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin PostMedia
 */
class PostMediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'url' => Storage::disk($this->disk)->url($this->path),
            'mimeType' => $this->mime_type,
            'size' => $this->size,
            'order' => $this->order,
            'ownerUuid' => $this->whenLoaded('owner', fn () => $this->owner->uuid),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
