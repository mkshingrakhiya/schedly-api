<?php

namespace App\Domain\Content\Http\Requests;

use App\Http\Requests\Api\V1FormRequest;
use App\Support\MediaPathResolver;
use Closure;
use Illuminate\Validation\Rule;

class AttachPostMediaRequest extends V1FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('managePosts', $this->workspace()) ?? false;
    }

    /**
     * @return array<string, array<int, Closure|string>>
     */
    public function rules(): array
    {
        $prefix = MediaPathResolver::workspaceUploadPrefix($this->workspace());

        return [
            'path' => [
                'required',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, Closure $fail) use ($prefix): void {
                    if (! is_string($value)) {
                        $fail('The path must be a string.');

                        return;
                    }

                    if (str_starts_with($value, '/') || str_contains($value, '..')) {
                        $fail('The path is invalid.');

                        return;
                    }

                    if (! str_starts_with($value, $prefix)) {
                        $fail('The path must use the S3 key prefix for this workspace.');

                        return;
                    }
                },
            ],
            'mime_type' => ['required', 'string', Rule::in(['image/jpeg', 'image/png', 'video/mp4'])],
            'size' => ['required', 'integer', 'min:1', 'max:52428800'],
        ];
    }
}
