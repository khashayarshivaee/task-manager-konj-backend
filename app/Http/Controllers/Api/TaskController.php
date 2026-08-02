<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class TaskController extends Controller
{
    /**
     * Get tasks belonging to a project.
     */
    public function index(
        Workspace $workspace,
        Project $project
    ): JsonResponse {
        $this->ensureProjectBelongsToWorkspace(
            $workspace,
            $project
        );

        Gate::authorize(
            'view',
            $workspace
        );

        $tasks = $project
            ->tasks()
            ->with([
                'creator:id,name,email',
                'assignee:id,name,email',
            ])
            ->orderBy('status')
            ->orderBy('position')
            ->latest('id')
            ->get();

        return response()->json([
            'data' => [
                'tasks' => $tasks,
            ],
        ]);
    }

    /**
     * Create a task inside a project.
     */
    public function store(
        StoreTaskRequest $request,
        Workspace $workspace,
        Project $project
    ): JsonResponse {
        $this->ensureProjectBelongsToWorkspace(
            $workspace,
            $project
        );

        Gate::authorize(
            'view',
            $workspace
        );

        $validated = $request->validated();

        $status = TaskStatus::from(
            $validated['status']
            ?? TaskStatus::Backlog->value
        );

        $task = $project
            ->tasks()
            ->create([
                'created_by' => $request->user()->id,

                'assigned_to' =>
                    $validated['assigned_to'] ?? null,

                'title' => $validated['title'],

                'description' =>
                    $validated['description'] ?? null,

                'status' => $status,

                'priority' =>
                    $validated['priority']
                    ?? TaskPriority::Medium,

                'starts_at' =>
                    $validated['starts_at'] ?? null,

                'due_at' =>
                    $validated['due_at'] ?? null,

                'completed_at' =>
                    $status->isCompleted()
                        ? now()
                        : null,

                'position' =>
                    $this->nextPosition(
                        $project,
                        $status
                    ),
            ]);

        $task->load([
            'creator:id,name,email',
            'assignee:id,name,email',
        ]);

        return response()->json([
            'message' => 'Task created successfully.',

            'data' => [
                'task' => $task,
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Get a single task.
     */
    public function show(
        Workspace $workspace,
        Project $project,
        Task $task
    ): JsonResponse {
        $this->ensureProjectBelongsToWorkspace(
            $workspace,
            $project
        );

        $this->ensureTaskBelongsToProject(
            $project,
            $task
        );

        Gate::authorize(
            'view',
            $workspace
        );

        $task->load([
            'creator:id,name,email',
            'assignee:id,name,email',
        ]);

        return response()->json([
            'data' => [
                'task' => $task,
            ],
        ]);
    }

    /**
     * Update a task.
     */
    public function update(
        UpdateTaskRequest $request,
        Workspace $workspace,
        Project $project,
        Task $task
    ): JsonResponse {
        $this->ensureProjectBelongsToWorkspace(
            $workspace,
            $project
        );

        $this->ensureTaskBelongsToProject(
            $project,
            $task
        );

        Gate::authorize(
            'view',
            $workspace
        );

        $validated = $request->validated();

        $status = TaskStatus::from(
            $validated['status']
        );

        $position = $task->position;

        if ($task->status !== $status) {
            $position = $this->nextPosition(
                $project,
                $status
            );
        }

        $completedAt = $task->completed_at;

        if (
            $status->isCompleted() &&
            !$task->status->isCompleted()
        ) {
            $completedAt = now();
        }

        if (!$status->isCompleted()) {
            $completedAt = null;
        }

        $task->update([
            'assigned_to' =>
                $validated['assigned_to'] ?? null,

            'title' => $validated['title'],

            'description' =>
                $validated['description'] ?? null,

            'status' => $status,

            'priority' =>
                TaskPriority::from(
                    $validated['priority']
                ),

            'starts_at' =>
                $validated['starts_at'] ?? null,

            'due_at' =>
                $validated['due_at'] ?? null,

            'completed_at' => $completedAt,

            'position' => $position,
        ]);

        $task->load([
            'creator:id,name,email',
            'assignee:id,name,email',
        ]);

        return response()->json([
            'message' => 'Task updated successfully.',

            'data' => [
                'task' => $task,
            ],
        ]);
    }

    /**
     * Delete a task.
     */
    public function destroy(
        Workspace $workspace,
        Project $project,
        Task $task
    ): JsonResponse {
        $this->ensureProjectBelongsToWorkspace(
            $workspace,
            $project
        );

        $this->ensureTaskBelongsToProject(
            $project,
            $task
        );

        Gate::authorize(
            'view',
            $workspace
        );

        $this->authorizeTaskDeletion(
            request()->user(),
            $workspace,
            $task
        );

        $task->delete();

        return response()->json([
            'message' => 'Task deleted successfully.',
        ]);
    }

    private function authorizeTaskDeletion(
        User $user,
        Workspace $workspace,
        Task $task
    ): void {
        if ($task->created_by === $user->id) {
            return;
        }

        if (
            Gate::allows(
                'manageProjects',
                $workspace
            )
        ) {
            return;
        }

        abort(Response::HTTP_FORBIDDEN);
    }

    private function nextPosition(
        Project $project,
        TaskStatus $status
    ): int {
        $maximumPosition = (int) $project
            ->tasks()
            ->where(
                'status',
                $status->value
            )
            ->max('position');

        return $maximumPosition + 1;
    }

    private function ensureProjectBelongsToWorkspace(
        Workspace $workspace,
        Project $project
    ): void {
        abort_unless(
            $project->workspace_id === $workspace->id,
            Response::HTTP_NOT_FOUND
        );
    }

    private function ensureTaskBelongsToProject(
        Project $project,
        Task $task
    ): void {
        abort_unless(
            $task->project_id === $project->id,
            Response::HTTP_NOT_FOUND
        );
    }
}
