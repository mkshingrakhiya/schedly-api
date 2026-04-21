<?php

namespace App\Domain\Content\Http\Requests;

use App\Domain\Content\Models\Channel;
use App\Http\Requests\Api\V1FormRequest;

class DisconnectChannelRequest extends V1FormRequest
{
    public function authorize(): bool
    {
        $channel = $this->route('channel');
        if (! $channel instanceof Channel) {
            return false;
        }

        if ($channel->workspace_id !== $this->workspace()->id) {
            abort(404);
        }

        return $this->user()?->can('manageChannels', $this->workspace()) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
