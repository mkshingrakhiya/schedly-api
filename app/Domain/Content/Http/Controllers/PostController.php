<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Http\Requests\DestroyPostRequest;
use App\Domain\Content\Http\Requests\IndexPostsRequest;
use App\Domain\Content\Http\Requests\ShowPostRequest;
use App\Domain\Content\Http\Requests\StorePostRequest;
use App\Domain\Content\Http\Requests\UpdatePostRequest;
use App\Domain\Content\Http\Resources\PostResource;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Services\PostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PostController
{
    public function __construct(private PostService $postService) {}

    public function index(IndexPostsRequest $request): JsonResponse
    {
        $posts = $this->postService->index(
            $request->workspace(),
            $request->integer('per_page', 15),
        );

        return PostResource::collection($posts)->response();
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $post = $this->postService->create(
            $request->workspace(),
            $request->user(),
            $request->validated(),
        );

        return PostResource::make($post)->response()->setStatusCode(201);
    }

    public function show(ShowPostRequest $request, Post $post): JsonResponse
    {
        $post = $this->postService->get($request->workspace(), $post);

        return PostResource::make($post)->response();
    }

    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        $post = $this->postService->update(
            $post,
            $request->workspace(),
            $request->validated(),
        );

        return PostResource::make($post)->response();
    }

    public function destroy(DestroyPostRequest $request, Post $post): Response
    {
        $this->postService->delete($post, $request->workspace());

        return response()->noContent();
    }
}
