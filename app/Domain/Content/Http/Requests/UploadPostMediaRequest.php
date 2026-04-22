<?php

namespace App\Domain\Content\Http\Requests;

use App\Http\Requests\Api\V1FormRequest;

class UploadPostMediaRequest extends V1FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('managePosts', $this->workspace()) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,mp4', 'max:51200'],
        ];
    }
}
