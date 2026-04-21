<?php

namespace App\Domain\Content\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexPlatformsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
