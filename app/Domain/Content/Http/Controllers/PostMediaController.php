<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Http\Requests\AttachPostMediaRequest;
use App\Domain\Content\Http\Requests\DeletePostMediaRequest;
use App\Domain\Content\Http\Requests\UploadPostMediaRequest;
use App\Domain\Content\Http\Resources\PostMediaResource;
use App\Domain\Content\Models\PostMedia;
use App\Domain\Content\Services\PostMediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PostMediaController
{
    public function __construct(private PostMediaService $postMediaService) {}

    public function upload(UploadPostMediaRequest $request): JsonResponse
    {
        $media = $this->postMediaService->upload(
            $request->workspace(),
            $request->user(),
            $request->file('file'),
        );

        return PostMediaResource::make($media)->response()->setStatusCode(201);
    }

    public function attach(AttachPostMediaRequest $request): JsonResponse
    {
        $media = $this->postMediaService->attach(
            $request->workspace(),
            $request->user(),
            $request->validated(),
        );

        return PostMediaResource::make($media)->response()->setStatusCode(201);
    }

    public function delete(DeletePostMediaRequest $request, PostMedia $media): Response
    {
        $this->postMediaService->delete($media);

        return response()->noContent();
    }
}
