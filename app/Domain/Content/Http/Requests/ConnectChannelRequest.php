<?php

namespace App\Domain\Content\Http\Requests;

use App\Http\Requests\Api\V1FormRequest;
use App\Models\Platform;
use Illuminate\Validation\Rule;

class ConnectChannelRequest extends V1FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageChannels', $this->workspace()) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $workspaceId = $this->workspace()->id;

        return [
            'platform_slug' => ['required', 'string', Rule::exists('platforms', 'slug')],
            'handle' => ['required', 'string', 'max:255'],
            'platform_account_id' => [
                'required', 'string', 'max:255',
                Rule::unique('channels', 'platform_account_id')
                    ->withoutTrashed()
                    ->where(function ($query) use ($workspaceId): void {
                        $query->where('workspace_id', $workspaceId);
                        $slug = $this->input('platform_slug');
                        $platformId = is_string($slug)
                            ? Platform::query()->where('slug', $slug)->value('id')
                            : null;

                        if ($platformId !== null) {
                            $query->where('platform_id', $platformId);
                        } else {
                            $query->whereRaw('1 = 0');
                        }
                    }),
            ],
            'access_token' => ['required', 'string'],
            'refresh_token' => ['nullable', 'string'],
            'token_expires_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array{platform_id: int, handle: string, platform_account_id: string, access_token: string, refresh_token: string|null, token_expires_at: \Illuminate\Support\Carbon|null}
     */
    public function createAttributes(): array
    {
        /** @var array{platform_slug: string, handle: string, platform_account_id: string, access_token: string, refresh_token?: string|null, token_expires_at?: string|null} $validated */
        $validated = $this->validated();

        $platform = Platform::query()->where('slug', $validated['platform_slug'])->firstOrFail();

        return [
            'platform_id' => $platform->id,
            'handle' => $validated['handle'],
            'platform_account_id' => $validated['platform_account_id'],
            'access_token' => $validated['access_token'],
            'refresh_token' => $validated['refresh_token'] ?? null,
            'token_expires_at' => isset($validated['token_expires_at'])
                ? $this->date('token_expires_at')
                : null,
        ];
    }
}
