<?php

namespace App\Domain\Content\Http\Requests;

use App\Http\Requests\Api\V1FormRequest;
use InvalidArgumentException;

class SocialPlatformConnectChannelsRequest extends V1FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageChannels', $this->workspace());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'channels' => ['required', 'array', 'min:1'],
            'channels.*.platform_slug' => ['required', 'string', 'in:'.$this->platformSlug()],
            'channels.*.platform_account_id' => ['required', 'string', 'max:255'],
            'channels.*.handle' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return list<array{platform_account_id: string, handle?: string|null}>
     */
    public function selectedChannels(): array
    {
        /** @var array{channels: list<array{platform_account_id: string, handle?: string|null}>} $validated */
        $validated = $this->validated();

        return $validated['channels'];
    }

    protected function platformSlug(): string
    {
        $platform = $this->route('platform');

        if (! is_string($platform) || $platform === '') {
            throw new InvalidArgumentException('Missing social platform route parameter.');
        }

        return $platform;
    }
}
