<?php

namespace App\Domain\Content\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SocialFacebookCallbackRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
            'state' => ['required', 'uuid'],
        ];
    }
}
