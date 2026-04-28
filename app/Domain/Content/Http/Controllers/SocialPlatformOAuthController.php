<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Http\Requests\SocialPlatformCallbackRequest;
use App\Domain\Content\Http\Requests\SocialPlatformConnectChannelsRequest;
use App\Domain\Content\Http\Requests\SocialPlatformConnectRequest;
use App\Domain\Content\Http\Resources\ChannelResource;
use App\Services\SocialPlatforms\PlatformOAuthService;
use Illuminate\Http\JsonResponse;

class SocialPlatformOAuthController
{
    public function __construct(private PlatformOAuthService $platformOAuthService) {}

    public function connect(SocialPlatformConnectRequest $request, string $platform): JsonResponse
    {
        $payload = $this->platformOAuthService->buildConnectionPayload(
            $request->workspace(),
            $request->user(),
            $platform,
        );

        return response()->json([
            'data' => [
                'authorizationUrl' => $payload['authorizationUrl'],
                'expiresAt' => $payload['expiresAt'],
            ],
        ]);
    }

    public function callback(SocialPlatformCallbackRequest $request, string $platform): JsonResponse
    {
        $channels = $this->platformOAuthService->handleCallback(
            $request->string('state')->toString(),
            $request->string('code')->toString(),
            $platform,
        );

        return response()->json([
            'data' => [
                'channels' => $channels,
            ],
        ]);
    }

    public function connectChannels(SocialPlatformConnectChannelsRequest $request, string $platform): JsonResponse
    {
        $channels = $this->platformOAuthService->storeSelectedChannels(
            $request->workspace(),
            $request->user(),
            $request->selectedChannels(),
            $platform,
        );

        return ChannelResource::collection($channels)->response()->setStatusCode(201);
    }
}
