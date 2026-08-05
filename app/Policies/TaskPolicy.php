<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;

class TaskPolicy
{
    /**
     * Any workspace member can start watching
     * a task.
     */
    public function watch(
        User $user,
        Task $task,
    ): bool {
        $workspace =
            $this->workspaceFor($task);

        return $workspace instanceof Workspace
            && $this->isWorkspaceMember(
                $workspace,
                $user,
            );
    }

    /**
     * Determine whether the user can access
     * and participate in the task discussion.
     */
    public function participateInDiscussion(
        User $user,
        Task $task,
    ): bool {
        $workspace =
            $this->workspaceFor($task);

        if (
            !$workspace instanceof Workspace
            || !$this->isWorkspaceMember(
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

        if ($task->created_by === $user->id) {
            return true;
        }

        if (
            $task
                ->assignees()
                ->whereKey($user->id)
                ->exists()
        ) {
            return true;
        }

        return $task
            ->watchers()
            ->whereKey($user->id)
            ->exists();
    }

    /**
     * Determine whether the user may stop
     * watching the task.
     */
    public function unwatch(
        User $user,
        Task $task,
    ): bool {
        if (!$this->watch($user, $task)) {
            return false;
        }

        /*
         * The task creator is a mandatory
         * watcher.
         */
        if ($task->created_by === $user->id) {
            return false;
        }

        /*
         * Active assignees are mandatory
         * watchers.
         */
        if (
            $task
                ->assignees()
                ->whereKey($user->id)
                ->exists()
        ) {
            return false;
        }

        return $task
            ->watchers()
            ->whereKey($user->id)
            ->exists();
    }

    private function workspaceFor(
        Task $task,
    ): ?Workspace {
        $workspaceId =
            $task
                ->project()
                ->value('workspace_id');

        if (!$workspaceId) {
            return null;
        }

        return Workspace::query()
            ->find((int) $workspaceId);
    }

    private function isWorkspaceMember(
        Workspace $workspace,
        User $user,
    ): bool {
        if ($workspace->owner_id === $user->id) {
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
        if ($workspace->owner_id === $user->id) {
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
