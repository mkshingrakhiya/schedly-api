<?php

namespace App\Domain\Content\Http\Requests;

use App\Domain\Content\Enums\PostStatus;
use App\Http\Requests\Api\V1FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends V1FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('managePosts', $this->workspace()) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $workspace = $this->workspace();

        return [
            'content' => ['required', 'string'],
            'status' => ['required', Rule::enum(PostStatus::class)],
            'targets' => ['required', 'array', 'min:1'],
            'targets.*.channel_uuid' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('channels', 'uuid')
                    ->where('workspace_id', $workspace->id)
                    ->whereNull('deleted_at'),
            ],
            'targets.*.scheduled_at' => ['required', 'date'],
            'targets.*.platform_options' => ['nullable', 'array'],
        ];
    }
}
