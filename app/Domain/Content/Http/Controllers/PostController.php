<?php

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Content\Http\Requests\DestroyPostRequest;
use App\Domain\Content\Http\Requests\ListPostsRequest;
use App\Domain\Content\Http\Requests\ShowPostRequest;
use App\Domain\Content\Http\Requests\StorePostRequest;
use App\Domain\Content\Http\Requests\UpdatePostRequest;
use App\Domain\Content\Http\Resources\PostResource;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Services\PostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PostController
{
    public function __construct(private PostService $postService)
    {
    }

    public function index(ListPostsRequest $request): AnonymousResourceCollection
    {
        $posts = $this->postService->paginateForWorkspace($request->workspace());

        return PostResource::collection($posts);
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $post = $this->postService->create(
            $request->workspace(),
            $request->user(),
            $request->validatedPayload(),
        );

        return PostResource::make($post)
            ->response()
            ->setStatusCode(201);
    }

    public function show(ShowPostRequest $request, Post $post): PostResource
    {
        $post->loadMissing(['creator', 'targets.channel']);

        return PostResource::make($post);
    }

    public function update(UpdatePostRequest $request, Post $post): PostResource
    {
        $post = $this->postService->update($post, $request->workspace(), $request->validatedPayload());

        return PostResource::make($post);
    }

    public function destroy(DestroyPostRequest $request, Post $post): Response
    {
        $this->postService->delete($post);

        return response()->noContent();
    }
}
