<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class TaskWatcherController extends Controller
{
    /**
     * Get task watchers and current-user state.
     */
    public function index(
        Workspace $workspace,
        Project $project,
        Task $task,
    ): JsonResponse {
        $user = request()->user();

        abort_unless(
            $user instanceof User,
            Response::HTTP_UNAUTHORIZED,
        );

        $this->assertTaskContext(
            $workspace,
            $project,
            $task,
        );

        Gate::authorize(
            'watch',
            $task,
        );

        return response()->json([
            'data' =>
                $this->buildWatcherState(
                    $task,
                    $user,
                ),
        ]);
    }

    /**
     * Add the authenticated user as a watcher.
     */
    public function store(
        Workspace $workspace,
        Project $project,
        Task $task,
    ): JsonResponse {
        $user = request()->user();

        abort_unless(
            $user instanceof User,
            Response::HTTP_UNAUTHORIZED,
        );

        $this->assertTaskContext(
            $workspace,
            $project,
            $task,
        );

        Gate::authorize(
            'watch',
            $task,
        );

        $task
            ->watchers()
            ->syncWithoutDetaching([
                $user->id,
            ]);

        return response()->json([
            'message' =>
                'You are now watching this task.',

            'data' =>
                $this->buildWatcherState(
                    $task,
                    $user,
                ),
        ]);
    }

    /**
     * Remove the authenticated user from
     * optional task watchers.
     */
    public function destroy(
        Workspace $workspace,
        Project $project,
        Task $task,
    ): JsonResponse {
        $user = request()->user();

        abort_unless(
            $user instanceof User,
            Response::HTTP_UNAUTHORIZED,
        );

        $this->assertTaskContext(
            $workspace,
            $project,
            $task,
        );

        Gate::authorize(
            'watch',
            $task,
        );

        if ($task->created_by === $user->id) {
            return response()->json(
                [
                    'message' =>
                        'The task creator must continue watching this task.',
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if (
            $task
                ->assignees()
                ->whereKey($user->id)
                ->exists()
        ) {
            return response()->json(
                [
                    'message' =>
                        'An assigned member cannot stop watching this task.',
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $task
            ->watchers()
            ->detach($user->id);

        return response()->json([
            'message' =>
                'You are no longer watching this task.',

            'data' =>
                $this->buildWatcherState(
                    $task,
                    $user,
                ),
        ]);
    }

    /**
     * @return array{
     *     watchers: list<array{
     *         id: int,
     *         name: string,
     *         email: string,
     *         avatar_path: string|null
     *     }>,
     *     is_watching: bool,
     *     can_comment: bool,
     *     can_unwatch: bool
     * }
     */
    private function buildWatcherState(
        Task $task,
        User $user,
    ): array {
        $watchers = $task
            ->watchers()
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.avatar_path',
            ])
            ->orderBy('users.name')
            ->get()
            ->map(
                fn (User $watcher): array => [
                    'id' => $watcher->id,
                    'name' => $watcher->name,
                    'email' => $watcher->email,

                    'avatar_path' =>
                        $watcher->avatar_path,
                ],
            )
            ->values()
            ->all();

        return [
            'watchers' => $watchers,

            'is_watching' =>
                $task
                    ->watchers()
                    ->whereKey($user->id)
                    ->exists(),

            'can_comment' =>
                Gate::allows(
                    'participateInDiscussion',
                    $task,
                ),

            'can_unwatch' =>
                Gate::allows(
                    'unwatch',
                    $task,
                ),
        ];
    }

    private function assertTaskContext(
        Workspace $workspace,
        Project $project,
        Task $task,
    ): void {
        abort_if(
            $project->workspace_id
                !== $workspace->id,
            Response::HTTP_NOT_FOUND,
        );

        abort_if(
            $task->project_id
                !== $project->id,
            Response::HTTP_NOT_FOUND,
        );
    }
}
