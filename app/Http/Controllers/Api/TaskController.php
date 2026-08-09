<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\TaskDiscussionReadService;
use Illuminate\Support\Facades\DB;
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
use App\Http\Requests\Task\UpdateTaskStatusRequest;
use App\Enums\ProjectActivitySubjectType;
use App\Enums\ProjectActivityType;
use App\Services\ProjectActivityLogger;
use Illuminate\Http\Request;

use App\Http\Requests\Task\ListProjectTasksRequest;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
class TaskController extends Controller
{
 public function __construct(
     private readonly TaskDiscussionReadService $discussionRead,
     private readonly ProjectActivityLogger $activityLogger,
 ) {
 }
 /**
  * Get paginated tasks belonging to a project.
  */
 /**
  * Get paginated tasks belonging to a project.
  */
 public function index(
     ListProjectTasksRequest $request,
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

     $user = $request->user();

     abort_unless(
         $user instanceof User,
         Response::HTTP_UNAUTHORIZED,
     );

     $query = $project
         ->tasks()
         ->getQuery()
         ->withUnreadCommentsCount(
             $user->id,
         )
         ->with([
             'creator:id,name,email,avatar_path',

             'assignee:id,name,email,avatar_path',

             'assignees:id,name,email,avatar_path',
         ]);

     return $this->paginateTaskList(
         $query,
         $request->validated()
     );
 }

 /**
  * Get paginated tasks belonging to
  * all projects inside a workspace.
  */
 public function workspaceIndex(
     ListProjectTasksRequest $request,
     Workspace $workspace
 ): JsonResponse {
     Gate::authorize(
         'view',
         $workspace
     );

     $user = $request->user();

     abort_unless(
         $user instanceof User,
         Response::HTTP_UNAUTHORIZED,
     );

     $query = Task::query()
         ->whereHas(
             'project',
             function (
                 Builder $projectQuery
             ) use ($workspace): void {
                 $projectQuery->where(
                     'workspace_id',
                     $workspace->id
                 );
             }
         )
         ->withUnreadCommentsCount(
             $user->id,
         )
         ->with([
             'project:id,workspace_id,name,slug,status,color',

             'creator:id,name,email,avatar_path',

             'assignee:id,name,email,avatar_path',

             'assignees:id,name,email,avatar_path',
         ]);

     return $this->paginateTaskList(
         $query,
         $request->validated()
     );
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

       $assigneeIds =
           $this->normalizeAssigneeIds(
               $validated
           );

       $task = DB::transaction(
           function () use (
               $request,
               $project,
               $validated,
               $status,
               $assigneeIds
           ): Task {
               $task = $project
                   ->tasks()
                   ->create([
                       'created_by' =>
                           $request->user()->id,

                       /*
                        * Temporary legacy field.
                        *
                        * It remains until the frontend
                        * fully migrates to assignees.
                        */
                       'assigned_to' =>
                           $assigneeIds[0] ?? null,

                       'title' =>
                           $validated['title'],

                       'description' =>
                           $validated[
                               'description'
                           ] ?? null,

                       'status' => $status,

                       'priority' =>
                           $validated['priority']
                           ?? TaskPriority::Medium,

                       'starts_at' =>
                           $validated[
                               'starts_at'
                           ] ?? null,

                       'due_at' =>
                           $validated[
                               'due_at'
                           ] ?? null,

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

               $this->syncTaskParticipants(
                   $task,
                   $assigneeIds,
                   $request->user()
               );

               return $task;
           }
       );

    $task = $this->loadTaskForResponse(
        $task,
        (int) $request->user()->id,
    );
    $this->activityLogger->log(
              project: $project,
              type:
                  ProjectActivityType::TaskCreated,
              actor: $request->user(),
              subjectType:
                  ProjectActivitySubjectType::Task,
              subjectId: $task->id,
              subjectLabel: $task->title,
              metadata: [
                  'status' =>
                      $task->status->value,

                  'priority' =>
                      $task->priority->value,

                  'assignees' =>
                      $task->assignees
                          ->map(
                              fn (User $user): array => [
                                  'id' => $user->id,
                                  'name' => $user->name,
                              ]
                          )
                          ->values()
                          ->all(),
              ],
          );

       return response()->json([
           'message' =>
               'Task created successfully.',

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
        $user = request()->user();

        abort_unless(
            $user instanceof User,
            Response::HTTP_UNAUTHORIZED,
        );
$task = $this->loadTaskForResponse(
    $task,
    $user->id,
);

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
       $beforeState =
           $this->taskActivityState(
               $task
           );

       $beforeAssignees =
           $this->taskAssigneeActivityState(
               $task
           );

       $status = TaskStatus::from(
           $validated['status']
       );

       $assigneeIds =
           $this->normalizeAssigneeIds(
               $validated
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

       DB::transaction(
           function () use (
               $request,
               $task,
               $validated,
               $status,
               $assigneeIds,
               $completedAt,
               $position
           ): void {
               $task->update([
                   /*
                    * Temporary legacy field.
                    */
                   'assigned_to' =>
                       $assigneeIds[0] ?? null,

                   'title' =>
                       $validated['title'],

                   'description' =>
                       $validated[
                           'description'
                       ] ?? null,

                   'status' => $status,

                   'priority' =>
                       TaskPriority::from(
                           $validated['priority']
                       ),

                   'starts_at' =>
                       $validated[
                           'starts_at'
                       ] ?? null,

                   'due_at' =>
                       $validated[
                           'due_at'
                       ] ?? null,

                   'completed_at' =>
                       $completedAt,

                   'position' => $position,
               ]);

               $this->syncTaskParticipants(
                   $task,
                   $assigneeIds,
                   $request->user()
               );
           }
       );

      $task = $this->loadTaskForResponse(
          $task,
          (int) $request->user()->id,
      );

      $afterState =
          $this->taskActivityState(
              $task
          );

      $afterAssignees =
          $this->taskAssigneeActivityState(
              $task
          );

      $taskChanges = [];

      foreach (
          [
              'title',
              'description',
              'priority',
              'starts_at',
              'due_at',
          ] as $field
      ) {
          if (
              $beforeState[$field] ===
              $afterState[$field]
          ) {
              continue;
          }

          $taskChanges[$field] = [
              'from' =>
                  $beforeState[$field],

              'to' =>
                  $afterState[$field],
          ];
      }

      if ($taskChanges !== []) {
          $this->activityLogger->log(
              project: $project,
              type:
                  ProjectActivityType::TaskUpdated,
              actor: $request->user(),
              subjectType:
                  ProjectActivitySubjectType::Task,
              subjectId: $task->id,
              subjectLabel: $task->title,
              metadata: [
                  'changes' =>
                      $taskChanges,
              ],
          );
      }
      if (
          $beforeState['status'] !==
          $afterState['status']
      ) {
          $this->activityLogger->log(
              project: $project,
              type:
                  ProjectActivityType::TaskStatusChanged,
              actor: $request->user(),
              subjectType:
                  ProjectActivitySubjectType::Task,
              subjectId: $task->id,
              subjectLabel: $task->title,
              metadata: [
                  'from' =>
                      $beforeState['status'],

                  'to' =>
                      $afterState['status'],
              ],
          );
      }
      $beforeAssigneeIds = array_column(
          $beforeAssignees,
          'id'
      );

      $afterAssigneeIds = array_column(
          $afterAssignees,
          'id'
      );

      if (
          $beforeAssigneeIds !==
          $afterAssigneeIds
      ) {
          $addedAssignees = array_values(
              array_filter(
                  $afterAssignees,
                  static fn (array $assignee): bool =>
                      !in_array(
                          $assignee['id'],
                          $beforeAssigneeIds,
                          true
                      )
              )
          );

          $removedAssignees = array_values(
              array_filter(
                  $beforeAssignees,
                  static fn (array $assignee): bool =>
                      !in_array(
                          $assignee['id'],
                          $afterAssigneeIds,
                          true
                      )
              )
          );

          $this->activityLogger->log(
              project: $project,
              type:
                  ProjectActivityType::TaskAssigneesChanged,
              actor: $request->user(),
              subjectType:
                  ProjectActivitySubjectType::Task,
              subjectId: $task->id,
              subjectLabel: $task->title,
              metadata: [
                  'added' =>
                      $addedAssignees,

                  'removed' =>
                      $removedAssignees,
              ],
          );
      }


       return response()->json([
           'message' =>
               'Task updated successfully.',

           'data' => [
               'task' => $task,
           ],
       ]);
   }

   /**
    * Update only the task status.
    */
   public function updateStatus(
       UpdateTaskStatusRequest $request,
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

       $previousStatus =
           $task->status->value;

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

       DB::transaction(
           function () use (
               $request,
               $task,
               $status,
               $position,
               $completedAt
           ): void {
               $task->update([
                   'status' => $status,
                   'position' => $position,
                   'completed_at' => $completedAt,
               ]);

               /*
            /*
             * A user who changes the task becomes
             * a watcher without resetting an existing
             * read position.
             */
            $this->discussionRead
                ->ensureWatchingAtLatest(
                    $task,
                    $request->user(),
                );
           }
       );

     $task = $this->loadTaskForResponse(
         $task,
         (int) $request->user()->id,
     );
     if (
         $previousStatus !==
         $task->status->value
     ) {
         $this->activityLogger->log(
             project: $project,
             type:
                 ProjectActivityType::TaskStatusChanged,
             actor: $request->user(),
             subjectType:
                 ProjectActivitySubjectType::Task,
             subjectId: $task->id,
             subjectLabel: $task->title,
             metadata: [
                 'from' =>
                     $previousStatus,

                 'to' =>
                     $task->status->value,
             ],
         );
     }

       return response()->json([
           'message' =>
               'Task status updated successfully.',

           'data' => [
               'task' => $task,
           ],
       ]);
   }

    /**
     * Delete a task.
     */
    public function destroy(
        Request $request,
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

        $user = $request->user();

        abort_unless(
            $user instanceof User,
            Response::HTTP_UNAUTHORIZED,
        );

        $this->authorizeTaskDeletion(
            $user,
            $workspace,
            $task
        );

        $taskId = $task->id;
        $taskTitle = $task->title;

        $task->delete();

        $this->activityLogger->log(
            project: $project,
            type:
                ProjectActivityType::TaskDeleted,
            actor: $user,
            subjectType:
                ProjectActivitySubjectType::Task,
            subjectId: $taskId,
            subjectLabel: $taskTitle,
        );

        return response()->json([
            'message' =>
                'Task deleted successfully.',
        ]);
    }
    /**
     * Apply task list filters, sorting and
     * pagination to a prepared task query.
     *
     * @param array<string, mixed> $validated
     */
    private function paginateTaskList(
        Builder $query,
        array $validated
    ): JsonResponse {
        if (!empty($validated['search'])) {
            $search =
                (string) $validated['search'];

            $query->where(
                function (
                    Builder $taskQuery
                ) use ($search): void {
                    $taskQuery
                        ->where(
                            'title',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'description',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        if (!empty($validated['status'])) {
            $query->where(
                'status',
                $validated['status']
            );
        }

        if (!empty($validated['priority'])) {
            $query->where(
                'priority',
                $validated['priority']
            );
        }

        if (!empty($validated['assignee'])) {
            $assigneeId =
                (int) $validated['assignee'];

            $query->whereHas(
                'assignees',
                function (
                    Builder $assigneeQuery
                ) use ($assigneeId): void {
                    $assigneeQuery->where(
                        'users.id',
                        $assigneeId
                    );
                }
            );
        }

        $this->applyTaskDueFilter(
            $query,
            $validated['due'] ?? null
        );

        $this->applyTaskListSorting(
            $query,
            (string) $validated['sort'],
            (string) $validated['direction']
        );

        $tasks = $query->paginate(
            (int) $validated['per_page'],
            ['*'],
            'page',
            (int) $validated['page']
        );

        return response()->json([
            'data' => [
                'tasks' =>
                    $tasks->items(),
            ],

            'meta' => [
                'current_page' =>
                    $tasks->currentPage(),

                'per_page' =>
                    $tasks->perPage(),

                'total' =>
                    $tasks->total(),

                'last_page' =>
                    $tasks->lastPage(),

                'from' =>
                    $tasks->firstItem(),

                'to' =>
                    $tasks->lastItem(),

                'has_more_pages' =>
                    $tasks->hasMorePages(),
            ],
        ]);
    }

    private function applyTaskDueFilter(
        Builder $query,
        ?string $due
    ): void {
        if ($due === null) {
            return;
        }

        $today = CarbonImmutable::today();

        if ($due === 'this_week') {
            $start = $today->startOfWeek(
                CarbonInterface::MONDAY
            );

            $end = $today->endOfWeek(
                CarbonInterface::SUNDAY
            );

            $query->whereBetween(
                'due_at',
                [
                    $start->toDateString(),
                    $end->toDateString(),
                ]
            );

            return;
        }

        if ($due === 'this_month') {
            $query->whereBetween(
                'due_at',
                [
                    $today
                        ->startOfMonth()
                        ->toDateString(),

                    $today
                        ->endOfMonth()
                        ->toDateString(),
                ]
            );

            return;
        }

        if ($due === 'this_year') {
            $query->whereBetween(
                'due_at',
                [
                    $today
                        ->startOfYear()
                        ->toDateString(),

                    $today
                        ->endOfYear()
                        ->toDateString(),
                ]
            );

            return;
        }

        if ($due === 'overdue') {
            $query
                ->whereNotNull('due_at')
                ->whereDate(
                    'due_at',
                    '<',
                    $today->toDateString()
                )
                ->where(
                    'status',
                    '!=',
                    TaskStatus::Completed->value
                );

            return;
        }

        if ($due === 'no_due_date') {
            $query->whereNull('due_at');
        }
    }

    private function applyTaskListSorting(
        Builder $query,
        string $sort,
        string $direction
    ): void {
        $statusOrder = <<<'SQL'
    CASE status
        WHEN 'backlog' THEN 0
        WHEN 'todo' THEN 1
        WHEN 'in_progress' THEN 2
        WHEN 'in_review' THEN 3
        WHEN 'completed' THEN 4
        ELSE 5
    END
    SQL;

        $priorityOrder = <<<'SQL'
    CASE priority
        WHEN 'low' THEN 0
        WHEN 'medium' THEN 1
        WHEN 'high' THEN 2
        WHEN 'urgent' THEN 3
        ELSE 4
    END
    SQL;

        if (
            $sort === 'workflow' ||
            $sort === 'status'
        ) {
            $query
                ->orderByRaw(
                    "{$statusOrder} {$direction}"
                )
                ->orderBy(
                    'position',
                    $direction
                )
                ->orderByDesc('id');

            return;
        }

        if ($sort === 'priority') {
            $query
                ->orderByRaw(
                    "{$priorityOrder} {$direction}"
                )
                ->orderByDesc('id');

            return;
        }

        if ($sort === 'due_at') {
            $query
                ->orderByRaw(
                    'due_at IS NULL ASC'
                )
                ->orderBy(
                    'due_at',
                    $direction
                )
                ->orderByDesc('id');

            return;
        }

        $query
            ->orderBy(
                $sort,
                $direction
            )
            ->orderByDesc('id');
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

    /**
     * @param array<string, mixed> $validated
     *
     * @return array<int, int>
     */
    private function normalizeAssigneeIds(
        array $validated
    ): array {
        return collect(
            $validated['assignee_ids'] ?? []
        )
            ->map(
                fn ($userId): int =>
                    (int) $userId
            )
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Synchronize task assignees and automatic
     * watchers.
     *
     * Removed assignees remain watchers until
     * they explicitly stop watching the task.
     *
     * @param array<int, int> $assigneeIds
     */
    private function syncTaskParticipants(
        Task $task,
        array $assigneeIds,
        User $actor
    ): void {
        $assigneeSyncData = collect(
            $assigneeIds
        )
            ->mapWithKeys(
                fn (int $userId): array => [
                    $userId => [
                        'assigned_by' =>
                            $actor->id,
                    ],
                ]
            )
            ->all();

        $task
            ->assignees()
            ->sync($assigneeSyncData);

        $watcherIds = collect([
            $task->created_by,
            $actor->id,
            ...$assigneeIds,
        ])
            ->filter()
            ->map(
                fn ($userId): int =>
                    (int) $userId
            )
            ->unique()
            ->values()
            ->all();

      foreach ($watcherIds as $watcherId) {
          $this->discussionRead
              ->ensureWatchingAtLatest(
                  $task,
                  $watcherId,
              );
      }
    }
    private function loadTaskForResponse(
        Task $task,
        int $userId,
    ): Task {
        return Task::query()
            ->withUnreadCommentsCount(
                $userId,
            )
            ->with([
                'creator:id,name,email,avatar_path',

                'assignee:id,name,email,avatar_path',

                'assignees:id,name,email,avatar_path',

                'watchers:id,name,email,avatar_path',
            ])
            ->findOrFail(
                $task->id,
            );
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

    /**
     * Get values tracked by the task
     * activity timeline.
     *
     * @return array<string, mixed>
     */
    private function taskActivityState(
        Task $task
    ): array {
        return [
            'title' =>
                $task->getRawOriginal(
                    'title'
                ),

            'description' =>
                $task->getRawOriginal(
                    'description'
                ),

            'status' =>
                $task->getRawOriginal(
                    'status'
                ),

            'priority' =>
                $task->getRawOriginal(
                    'priority'
                ),

            'starts_at' =>
                $task->getRawOriginal(
                    'starts_at'
                ),

            'due_at' =>
                $task->getRawOriginal(
                    'due_at'
                ),
        ];
    }
    /**
     * Get the task assignees tracked
     * by the activity timeline.
     *
     * @return array<int, array{
     *     id: int,
     *     name: string
     * }>
     */
    private function taskAssigneeActivityState(
        Task $task
    ): array {
        return $task
            ->assignees()
            ->orderBy('users.id')
            ->get([
                'users.id',
                'users.name',
            ])
            ->map(
                static fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                ]
            )
            ->values()
            ->all();
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
