<?php

namespace App\Domain\Content\Http\Requests;

use App\Domain\Content\Enums\PostStatus;
use App\Http\Requests\Api\V1FormRequest;
use App\Models\Post;
use Illuminate\Validation\Rule;

class UpdatePostRequest extends V1FormRequest
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

        return $this->user()?->can('update', $post) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $workspace = $this->workspace();

        return [
            'content' => ['sometimes', 'string'],
            'status' => ['sometimes', Rule::enum(PostStatus::class)],
            'targets' => ['sometimes', 'nullable', 'array', 'min:1'],
            'targets.*.channel_uuid' => [
                'required_with:targets',
                'uuid',
                'distinct',
                Rule::exists('channels', 'uuid')
                    ->where('workspace_id', $workspace->id)
                    ->whereNull('deleted_at'),
            ],
            'targets.*.scheduled_at' => ['required_with:targets.*.channel_uuid', 'date'],
            'targets.*.platform_options' => ['nullable', 'array'],
        ];
    }
}
