<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostMedia;
use App\Models\User;
use App\Models\Workspace;
use App\Support\MediaPathResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

class PostMediaService
{
    public function upload(Workspace $workspace, User $owner, UploadedFile $file): PostMedia
    {
        $disk = config('filesystems.default');
        $mediaUuid = Uuid::uuid7()->toString();

        $directory = MediaPathResolver::workspacePostMediaDirectory($workspace, $mediaUuid);
        $storedPath = $file->store($directory, $disk);

        $media = PostMedia::query()->create([
            'uuid' => $mediaUuid,
            'workspace_id' => $workspace->id,
            'owner_id' => $owner->id,
            'post_id' => null,
            'disk' => $disk,
            'path' => $storedPath,
            'mime_type' => (string) $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return $media->load('owner');
    }

    /**
     * @param  array{path: string, mime_type: string, size: int}  $validated
     */
    public function attach(Workspace $workspace, User $owner, array $validated): PostMedia
    {
        $media = PostMedia::query()->create([
            'workspace_id' => $workspace->id,
            'owner_id' => $owner->id,
            'disk' => $validated['disk'] ?? config('filesystems.default'),
            'path' => $validated['path'],
            'mime_type' => $validated['mime_type'],
            'size' => $validated['size'],
        ]);

        return $media->load('owner');
    }

    public function delete(PostMedia $media): void
    {
        $media->delete();
    }

    /**
     * @param  list<string>  $uuids
     */
    public function link(Workspace $workspace, Post $post, array $uuids): void
    {
        $incoming = $this->dedupeOrderedUuids($uuids);
        $this->assertMediaCanLinkToPost($workspace, $post, $incoming);
        $this->attachPost($workspace, $post, $incoming);
    }

    /**
     * @param  list<string>  $uuids
     */
    public function sync(Post $post, Workspace $workspace, array $uuids): void
    {
        $incoming = $this->dedupeOrderedUuids($uuids);
        $existing = $post->media()->pluck('uuid')->all();

        $toDelete = array_values(array_diff($existing, $incoming));
        foreach ($toDelete as $uuid) {
            $media = $post->media()->where('uuid', $uuid)->first();
            if ($media !== null) {
                $this->delete($media);
            }
        }

        $this->assertMediaCanLinkToPost($workspace, $post, $incoming);
        $this->attachPost($workspace, $post, $incoming);
    }

    /**
     * @param  list<string>  $uuids
     * @return list<string>
     */
    private function dedupeOrderedUuids(array $uuids): array
    {
        $seen = [];
        $out = [];

        foreach ($uuids as $uuid) {
            if (isset($seen[$uuid])) {
                continue;
            }
            $seen[$uuid] = true;
            $out[] = $uuid;
        }

        return $out;
    }

    /**
     * @param  list<string>  $uuids
     */
    private function assertMediaCanLinkToPost(Workspace $workspace, Post $post, array $uuids): void
    {
        if ($uuids === []) {
            return;
        }

        $media = PostMedia::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('uuid', $uuids)
            ->get()
            ->keyBy('uuid');

        foreach ($uuids as $uuid) {
            $row = $media->get($uuid);
            if ($row === null) {
                throw ValidationException::withMessages([
                    'media_uuids' => ['One or more media items are invalid for this workspace.'],
                ]);
            }

            if ($row->post_id !== null && $row->post_id !== $post->id) {
                throw ValidationException::withMessages([
                    'media_uuids' => ['One or more media items are already linked to another post.'],
                ]);
            }
        }
    }

    /**
     * @param  list<string>  $uuids
     */
    private function attachPost(Workspace $workspace, Post $post, array $uuids): void
    {
        foreach ($uuids as $order => $uuid) {
            PostMedia::query()
                ->where('workspace_id', $workspace->id)
                ->where('uuid', $uuid)
                ->update([
                    'post_id' => $post->id,
                    'order' => $order,
                ]);
        }
    }
}
