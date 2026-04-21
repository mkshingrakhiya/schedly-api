<?php

namespace App\Support;

use App\Models\Workspace;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class WorkspaceHeader
{
    public const HEADER_NAME = 'X-Workspace-Uuid';

    public static function resolve(Request $request): Workspace
    {
        $uuid = $request->header(self::HEADER_NAME);

        if (blank($uuid)) {
            throw new HttpException(400, 'The '.self::HEADER_NAME.' header is required.');
        }

        return Workspace::query()->where('uuid', $uuid)->firstOrFail();
    }
}
