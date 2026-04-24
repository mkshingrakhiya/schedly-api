<?php

namespace Tests\Fixtures\Http\Requests;

use App\Http\Requests\Api\V1FormRequest;

/**
 * Minimal FormRequest for exercising {@see V1FormRequest::workspace()} in isolation.
 */
final class V1WorkspaceProbeRequest extends V1FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
