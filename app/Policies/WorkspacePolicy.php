<?php

namespace App\Policies;

use App\Domain\Workspaces\Enums\WorkspaceMemberRole;
use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    public function viewPosts(User $user, Workspace $workspace): bool
    {
        return $workspace->memberRoleFor($user) !== null;
    }

    public function managePosts(User $user, Workspace $workspace): bool
    {
        return $workspace->memberRoleFor($user) === WorkspaceMemberRole::Owner;
    }
}
