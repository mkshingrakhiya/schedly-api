<?php

namespace App\Domain\Content\Http\Requests;

use App\Http\Requests\Api\V1FormRequest;

class ListPostsRequest extends V1FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewPosts', $this->workspace()) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
