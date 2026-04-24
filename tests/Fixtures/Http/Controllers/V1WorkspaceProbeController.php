<?php

namespace Tests\Fixtures\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Tests\Fixtures\Http\Requests\V1WorkspaceProbeRequest;

final class V1WorkspaceProbeController
{
    public function show(V1WorkspaceProbeRequest $request): JsonResponse
    {
        return response()->json([
            'workspaceUuid' => $request->workspace()->uuid,
        ]);
    }
}
