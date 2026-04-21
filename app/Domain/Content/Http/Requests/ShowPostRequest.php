<?php

namespace App\Domain\Content\Http\Requests;

use App\Domain\Content\Models\Post;
use App\Http\Requests\Api\V1FormRequest;

class ShowPostRequest extends V1FormRequest
{
    public function authorize(): bool
    {
        $post = $this->route('post');
        if (! $post instanceof Post) {
            return false;
        }

        if ($post->workspace_id !== $this->workspace()->id) {
            abort(404);
        }

        return $this->user()?->can('view', $post) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
