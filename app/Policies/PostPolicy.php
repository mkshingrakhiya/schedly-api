<?php

namespace App\Policies;

use App\Domain\Content\Models\Post;
use App\Domain\Workspaces\Enums\WorkspaceMemberRole;
use App\Models\User;

class PostPolicy
{
    public function view(User $user, Post $post): bool
    {
        return $post->workspace->memberRoleFor($user) !== null;
    }

    public function update(User $user, Post $post): bool
    {
        return $post->workspace->memberRoleFor($user) === WorkspaceMemberRole::Owner;
    }

    public function delete(User $user, Post $post): bool
    {
        return $this->update($user, $post);
    }
}
