<?php

namespace App\Policies;

use App\Enums\WorkspaceMemberRole;
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

    public function viewChannels(User $user, Workspace $workspace): bool
    {
        return $workspace->memberRoleFor($user) !== null;
    }

    public function manageChannels(User $user, Workspace $workspace): bool
    {
        return $workspace->memberRoleFor($user) === WorkspaceMemberRole::Owner;
    }
}
