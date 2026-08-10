<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Models\WorkspaceNote;

class WorkspaceNotePolicy
{
    /**
     * Any workspace member can list notes.
     */
    public function viewAny(
        User $user,
        Workspace $workspace,
    ): bool {
        return $this->isWorkspaceMember(
            $workspace,
            $user,
        );
    }

    /**
     * Any workspace member can create notes.
     */
    public function create(
        User $user,
        Workspace $workspace,
    ): bool {
        return $this->isWorkspaceMember(
            $workspace,
            $user,
        );
    }

    /**
     * Any current workspace member
     * can view a note.
     */
    public function view(
        User $user,
        WorkspaceNote $note,
    ): bool {
        return $this->isWorkspaceMember(
            $note->workspace,
            $user,
        );
    }

    /**
     * Owner/Admin may edit every note.
     * Members may edit only their own notes.
     */
    public function update(
        User $user,
        WorkspaceNote $note,
    ): bool {
        $workspace = $note->workspace;

        if (
            !$this->isWorkspaceMember(
                $workspace,
                $user,
            )
        ) {
            return false;
        }

        if (
            $this->isWorkspaceManager(
                $workspace,
                $user,
            )
        ) {
            return true;
        }

        return $note->author_id ===
            $user->id;
    }

    /**
     * Owner/Admin may delete every note.
     * Members may delete only their own notes.
     */
    public function delete(
        User $user,
        WorkspaceNote $note,
    ): bool {
        $workspace = $note->workspace;

        if (
            !$this->isWorkspaceMember(
                $workspace,
                $user,
            )
        ) {
            return false;
        }

        if (
            $this->isWorkspaceManager(
                $workspace,
                $user,
            )
        ) {
            return true;
        }

        return $note->author_id ===
            $user->id;
    }

    private function isWorkspaceMember(
        Workspace $workspace,
        User $user,
    ): bool {
        if (
            $workspace->owner_id ===
            $user->id
        ) {
            return true;
        }

        return WorkspaceMembership::query()
            ->where(
                'workspace_id',
                $workspace->id,
            )
            ->where(
                'user_id',
                $user->id,
            )
            ->exists();
    }

    private function isWorkspaceManager(
        Workspace $workspace,
        User $user,
    ): bool {
        if (
            $workspace->owner_id ===
            $user->id
        ) {
            return true;
        }

        return WorkspaceMembership::query()
            ->where(
                'workspace_id',
                $workspace->id,
            )
            ->where(
                'user_id',
                $user->id,
            )
            ->whereIn(
                'role',
                [
                    WorkspaceRole::Owner
                        ->value,

                    WorkspaceRole::Admin
                        ->value,
                ],
            )
            ->exists();
    }
}
