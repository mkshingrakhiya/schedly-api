<?php

namespace App\Support;

use App\Models\Workspace;

final class MediaPathResolver
{
    /**
     * Base directory for post media in object storage (no leading/trailing slashes).
     * Uses {@see config('app.post_media_storage_path')}; `%s` is replaced with the workspace UUID.
     */
    public static function workspacePostMediaBase(Workspace $workspace): string
    {
        $template = (string) config('app.post_media_storage_path');

        return sprintf($template, $workspace->uuid);
    }

    /**
     * Directory used for a single upload (e.g. per media UUID segment).
     */
    public static function workspacePostMediaDirectory(Workspace $workspace, string $segment): string
    {
        return self::workspacePostMediaBase($workspace).'/'.$segment;
    }

    /**
     * Required key prefix for attach paths (includes trailing slash).
     */
    public static function workspaceUploadPrefix(Workspace $workspace): string
    {
        return self::workspacePostMediaBase($workspace).'/';
    }
}
