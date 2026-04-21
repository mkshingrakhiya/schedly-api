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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $workspaceId = $this->workspace()->id;

        return [
            'content' => ['required', 'string'],
            'status' => ['nullable', Rule::enum(PostStatus::class)],
            'targets' => ['sometimes', 'array'],
            'targets.*.channel_uuid' => [
                'required',
                'uuid',
                Rule::exists('channels', 'uuid')
                    ->where('workspace_id', $workspaceId)
                    ->withoutTrashed(),
            ],
            'targets.*.scheduled_at' => ['required', 'date'],
            'targets.*.published_at' => ['nullable', 'date'],
            'targets.*.platform_options' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedPayload(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return [
            'content' => $validated['content'],
            'status' => $validated['status'] ?? null,
            'targets' => $validated['targets'] ?? [],
        ];
    }
}
