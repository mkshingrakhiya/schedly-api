<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Http\Requests\IndexPlatformsRequest;
use App\Domain\Content\Http\Resources\PlatformResource;
use App\Models\Platform;
use Illuminate\Http\JsonResponse;

class PlatformController
{
    public function index(IndexPlatformsRequest $request): JsonResponse
    {
        $platforms = Platform::query()->orderBy('name')->get();

        return PlatformResource::collection($platforms)->response();
    }
}
