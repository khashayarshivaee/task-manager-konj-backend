<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    /**
     * Determine whether the user can view workspace content.
     */
    public function view(
        User $user,
        Workspace $workspace
    ): bool {
        return $workspace
            ->memberships()
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Determine whether the user can manage workspace members.
     */
    public function manageMembers(
        User $user,
        Workspace $workspace
    ): bool {
        $membership = $workspace
            ->memberships()
            ->where('user_id', $user->id)
            ->first();

        return $membership
            ?->role
            ->canManageMembers() ?? false;
    }

    /**
     * Determine whether the user can update workspace settings.
     */
    public function update(
        User $user,
        Workspace $workspace
    ): bool {
        $membership = $workspace
            ->memberships()
            ->where('user_id', $user->id)
            ->first();

        return $membership
            ?->role
            ->canManageWorkspace() ?? false;
    }

    /**
     * Determine whether the user can permanently delete the workspace.
     */
    public function delete(
        User $user,
        Workspace $workspace
    ): bool {
        return $workspace->owner_id === $user->id;
    }

    /**
     * Determine whether the user can create or manage projects.
     */
    public function manageProjects(
        User $user,
        Workspace $workspace
    ): bool {
        $membership = $workspace
            ->memberships()
            ->where('user_id', $user->id)
            ->first();

        return $membership
            ?->role
            ->canManageProjects() ?? false;
    }
}
