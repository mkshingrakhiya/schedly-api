<?php

namespace App\Domain\Content\Http\Requests;

use App\Domain\Content\Models\PostMedia;
use App\Http\Requests\Api\V1FormRequest;

class DeletePostMediaRequest extends V1FormRequest
{
    public function authorize(): bool
    {
        $media = $this->route('media');
        if (! $media instanceof PostMedia) {
            return false;
        }

        if ($media->workspace_id !== $this->workspace()->id) {
            abort(404);
        }

        return $this->user()?->can('managePosts', $this->workspace()) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'source_uuid' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
