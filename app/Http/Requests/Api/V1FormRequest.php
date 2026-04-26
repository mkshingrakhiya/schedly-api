<?php

namespace App\Http\Requests\Api;

use App\Models\Workspace;
use App\Support\WorkspaceHeader;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base FormRequest for authenticated `/api/v1/*` routes that are scoped to a workspace
 * via the {@see WorkspaceHeader::HEADER_NAME} header.
 */
abstract class V1FormRequest extends FormRequest
{
    private ?Workspace $resolvedWorkspace = null;

    public function workspace(): Workspace
    {
        return $this->resolvedWorkspace ??= WorkspaceHeader::resolve($this);
    }
}
