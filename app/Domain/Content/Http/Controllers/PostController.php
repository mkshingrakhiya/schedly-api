<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Http\Requests\DestroyPostRequest;
use App\Domain\Content\Http\Requests\IndexPostsRequest;
use App\Domain\Content\Http\Requests\ShowPostRequest;
use App\Domain\Content\Http\Requests\StorePostRequest;
use App\Domain\Content\Http\Requests\UpdatePostRequest;
use App\Domain\Content\Http\Resources\PostResource;
use App\Domain\Content\Services\PostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PostController
{
    public function __construct(private PostService $posts)
    {
    }

    public function index(IndexPostsRequest $request): JsonResponse
    {
        $posts = $this->posts->paginateForWorkspace(
            $request->workspace(),
            $request->integer('per_page', 15),
        );

        return PostResource::collection($posts)->response();
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $post = $this->posts->create(
            $request->workspace(),
            $request->user(),
            $request->validated(),
        );

        return PostResource::make($post)->response()->setStatusCode(201);
    }

    public function show(ShowPostRequest $request, string $postUuid): JsonResponse
    {
        $post = $this->posts->get($request->workspace(), $postUuid);

        return PostResource::make($post)->response();
    }

    public function update(UpdatePostRequest $request, string $postUuid): JsonResponse
    {
        $workspace = $request->workspace();
        $post = $this->posts->update(
            $this->posts->get($workspace, $postUuid),
            $workspace,
            $request->validated(),
        );

        return PostResource::make($post)->response();
    }

    public function destroy(DestroyPostRequest $request, string $postUuid): Response
    {
        $this->posts->delete($this->posts->get($request->workspace(), $postUuid));

        return response()->noContent();
    }
}
