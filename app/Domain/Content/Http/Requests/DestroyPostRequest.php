<?php

namespace App\Domain\Content\Http\Requests;

use App\Http\Requests\Api\V1FormRequest;
use App\Models\Post;

class DestroyPostRequest extends V1FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->workspace();
        $post = Post::query()
            ->where('uuid', $this->route('postUuid'))
            ->where('workspace_id', $workspace->id)
            ->first();

        if ($post === null) {
            abort(404);
        }

        return $this->user()?->can('delete', $post) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
