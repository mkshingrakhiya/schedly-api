<?php

namespace App\Domain\Content\Http\Requests;

use App\Http\Requests\Api\V1FormRequest;

class SocialFacebookConnectRequest extends V1FormRequest
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
        return [];
    }
}
