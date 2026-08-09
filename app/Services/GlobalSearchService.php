<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class GlobalSearchService
{
    /**
     * @return array{
     *     workspaces: Collection<int, array<string, mixed>>,
     *     projects: Collection<int, array<string, mixed>>,
     *     tasks: Collection<int, array<string, mixed>>,
     *     comments: Collection<int, array<string, mixed>>
     * }
     */
    public function search(
        User $user,
        string $term,
        int $limit,
    ): array {
        return [
            'workspaces' =>
                $this->searchWorkspaces(
                    $user,
                    $term,
                    $limit,
                ),

            'projects' =>
                $this->searchProjects(
                    $user,
                    $term,
                    $limit,
                ),

            'tasks' =>
                $this->searchTasks(
                    $user,
                    $term,
                    $limit,
                ),

            'comments' =>
                $this->searchComments(
                    $user,
                    $term,
                    $limit,
                ),
        ];
    }

    private function searchWorkspaces(
        User $user,
        string $term,
        int $limit,
    ): Collection {
        return Workspace::query()
            ->whereHas(
                'memberships',
                fn (Builder $query) =>
                    $query->where(
                        'user_id',
                        $user->id,
                    ),
            )
            ->where(
                'name',
                'like',
                "%{$term}%",
            )
            ->orderByRaw(
                'CASE WHEN name LIKE ? THEN 0 ELSE 1 END',
                [
                    "{$term}%",
                ],
            )
            ->latest('id')
            ->limit($limit)
            ->get([
                'id',
                'name',
                'slug',
            ])
            ->map(
                static fn (
                    Workspace $workspace,
                ): array => [
                    'type' =>
                        'workspace',

                    'id' =>
                        $workspace->id,

                    'workspace_id' =>
                        $workspace->id,

                    'project_id' =>
                        null,

                    'task_id' =>
                        null,

                    'title' =>
                        $workspace->name,

                    'subtitle' =>
                        'Workspace',

                    'excerpt' =>
                        null,
                ],
            );
    }

    private function searchProjects(
        User $user,
        string $term,
        int $limit,
    ): Collection {
        return Project::query()
            ->whereHas(
                'workspace.memberships',
                fn (Builder $query) =>
                    $query->where(
                        'user_id',
                        $user->id,
                    ),
            )
            ->where(
                function (
                    Builder $query,
                ) use ($term): void {
                    $query
                        ->where(
                            'name',
                            'like',
                            "%{$term}%",
                        )
                        ->orWhere(
                            'description',
                            'like',
                            "%{$term}%",
                        );
                },
            )
            ->with([
                'workspace:id,name',
            ])
            ->orderByRaw(
                'CASE WHEN name LIKE ? THEN 0 ELSE 1 END',
                [
                    "{$term}%",
                ],
            )
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(
                static fn (
                    Project $project,
                ): array => [
                    'type' =>
                        'project',

                    'id' =>
                        $project->id,

                    'workspace_id' =>
                        $project->workspace_id,

                    'project_id' =>
                        $project->id,

                    'task_id' =>
                        null,

                    'title' =>
                        $project->name,

                    'subtitle' =>
                        $project->workspace->name,

                    'excerpt' =>
                        $project->description
                            ? Str::limit(
                                $project->description,
                                120,
                            )
                            : null,
                ],
            );
    }

    private function searchTasks(
        User $user,
        string $term,
        int $limit,
    ): Collection {
        return Task::query()
            ->whereHas(
                'project.workspace.memberships',
                fn (Builder $query) =>
                    $query->where(
                        'user_id',
                        $user->id,
                    ),
            )
            ->where(
                function (
                    Builder $query,
                ) use ($term): void {
                    $query
                        ->where(
                            'title',
                            'like',
                            "%{$term}%",
                        )
                        ->orWhere(
                            'description',
                            'like',
                            "%{$term}%",
                        );
                },
            )
            ->with([
                'project:id,workspace_id,name',
            ])
            ->orderByRaw(
                'CASE WHEN title LIKE ? THEN 0 ELSE 1 END',
                [
                    "{$term}%",
                ],
            )
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(
                static fn (
                    Task $task,
                ): array => [
                    'type' =>
                        'task',

                    'id' =>
                        $task->id,

                    'workspace_id' =>
                        $task->project
                            ->workspace_id,

                    'project_id' =>
                        $task->project_id,

                    'task_id' =>
                        $task->id,

                    'title' =>
                        $task->title,

                    'subtitle' =>
                        $task->project->name,

                    'excerpt' =>
                        $task->description
                            ? Str::limit(
                                $task->description,
                                120,
                            )
                            : null,
                ],
            );
    }

    private function searchComments(
        User $user,
        string $term,
        int $limit,
    ): Collection {
        return TaskComment::query()
            /*
             * The workspace itself must be
             * accessible to the user.
             */
            ->whereHas(
                'task.project.workspace.memberships',
                fn (Builder $query) =>
                    $query->where(
                        'user_id',
                        $user->id,
                    ),
            )

            /*
             * Match TaskPolicy::participateInDiscussion.
             */
            ->whereHas(
                'task',
                function (
                    Builder $taskQuery,
                ) use ($user): void {
                    $taskQuery->where(
                        function (
                            Builder $accessQuery,
                        ) use ($user): void {
                            $accessQuery
                                ->where(
                                    'created_by',
                                    $user->id,
                                )

                                ->orWhereHas(
                                    'assignees',
                                    fn (
                                        Builder $query,
                                    ) =>
                                        $query->where(
                                            'users.id',
                                            $user->id,
                                        ),
                                )

                                ->orWhereHas(
                                    'watchers',
                                    fn (
                                        Builder $query,
                                    ) =>
                                        $query->where(
                                            'users.id',
                                            $user->id,
                                        ),
                                )

                                ->orWhereHas(
                                    'project.workspace',
                                    function (
                                        Builder $workspaceQuery,
                                    ) use ($user): void {
                                        $workspaceQuery
                                            ->where(
                                                'owner_id',
                                                $user->id,
                                            )

                                            ->orWhereHas(
                                                'memberships',
                                                fn (
                                                    Builder $membershipQuery,
                                                ) =>
                                                    $membershipQuery
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
                                                        ),
                                            );
                                    },
                                );
                        },
                    );
                },
            )
            ->where(
                'body',
                'like',
                "%{$term}%",
            )
            ->with([
                'user:id,name,avatar_path',

                'task:id,project_id,title',

                'task.project:id,workspace_id,name',
            ])
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(
                static fn (
                    TaskComment $comment,
                ): array => [
                    'type' =>
                        'comment',

                    'id' =>
                        $comment->id,

                    'workspace_id' =>
                        $comment
                            ->task
                            ->project
                            ->workspace_id,

                    'project_id' =>
                        $comment
                            ->task
                            ->project_id,

                    'task_id' =>
                        $comment->task_id,

                    'title' =>
                        $comment->task->title,

                    'subtitle' =>
                        $comment->user->name,

                    'excerpt' =>
                        Str::limit(
                            $comment->body,
                            140,
                        ),
                ],
            );
    }
}
