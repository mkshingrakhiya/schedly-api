<?php

namespace App\Domain\Content\Http\Requests;

use App\Domain\Content\Enums\PostStatus;
use App\Domain\Content\Models\Post;
use App\Http\Requests\Api\V1FormRequest;
use Illuminate\Validation\Rule;

class UpdatePostRequest extends V1FormRequest
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

        return $this->user()?->can('update', $post) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $workspaceId = $this->workspace()->id;

        return [
            'content' => ['sometimes', 'string'],
            'status' => ['sometimes', Rule::enum(PostStatus::class)],
            'targets' => ['sometimes', 'array'],
            'targets.*.channelUuid' => [
                'required',
                'uuid',
                Rule::exists('channels', 'uuid')->where('workspace_id', $workspaceId),
            ],
            'targets.*.scheduledAt' => ['required', 'date'],
            'targets.*.publishedAt' => ['nullable', 'date'],
            'targets.*.platformOptions' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedPayload(): array
    {
        return $this->validated();
    }
}
