<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Http\Requests\ConnectFacebookChannelsRequest;
use App\Domain\Content\Http\Requests\SocialFacebookCallbackRequest;
use App\Domain\Content\Http\Requests\SocialFacebookConnectRequest;
use App\Domain\Content\Http\Resources\ChannelResource;
use App\Services\SocialPlatforms\FacebookOAuthService;
use Illuminate\Http\JsonResponse;

class FacebookSocialController
{
    public function __construct(private FacebookOAuthService $facebookOAuthService) {}

    public function connect(SocialFacebookConnectRequest $request): JsonResponse
    {
        $payload = $this->facebookOAuthService->buildConnectionPayload(
            $request->workspace(),
            $request->user(),
        );

        return response()->json([
            'data' => [
                'authorizationUrl' => $payload['authorizationUrl'],
                'expiresAt' => $payload['expiresAt'],
            ],
        ]);
    }

    public function callback(SocialFacebookCallbackRequest $request): JsonResponse
    {
        $channels = $this->facebookOAuthService->handleCallback(
            $request->string('state')->toString(),
            $request->string('code')->toString(),
        );

        return response()->json([
            'data' => [
                'channels' => $channels,
            ],
        ]);
    }

    // TODO: This will be the common endpoint to connect selected channels
    public function connectChannels(ConnectFacebookChannelsRequest $request): JsonResponse
    {
        $channels = $this->facebookOAuthService->storeSelectedChannels(
            $request->workspace(),
            $request->user(),
            $request->selectedChannels(),
        );

        return ChannelResource::collection($channels)->response()->setStatusCode(201);
    }
}
