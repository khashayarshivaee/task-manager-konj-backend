<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Task\ProjectTaskCalendarRequest;
use App\Models\Project;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

class ProjectTaskCalendarController
{
    public function __invoke(
        ProjectTaskCalendarRequest $request,
        Workspace $workspace,
        Project $project
    ): JsonResponse {
        abort_unless(
            (int) $project->workspace_id ===
            (int) $workspace->id,
            404
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

        $validated =
            $request->validated();

        $from =
            CarbonImmutable::createFromFormat(
                'Y-m-d',
                (string) $validated['from']
            );

        $to =
            CarbonImmutable::createFromFormat(
                'Y-m-d',
                (string) $validated['to']
            );

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

        $tasks = $project
            ->tasks()
           ->getQuery()
           ->withUnreadCommentsCount(
               $user->id,
           )
           ->with([
                'creator:id,name,email,avatar_path',

                'assignee:id,name,email,avatar_path',

                'assignees:id,name,email,avatar_path',
            ])
            ->whereNotNull('due_at')
            ->whereDate(
                'due_at',
                '>=',
                $from->toDateString()
            )
            ->whereDate(
                'due_at',
                '<=',
                $to->toDateString()
            )
            ->orderBy('due_at')
            ->orderByRaw($statusOrder)
            ->orderBy('position')
            ->orderByDesc('id')
            ->get();

        $withoutDueDate = $project
            ->tasks()
            ->whereNull('due_at')
            ->count();

        return response()->json([
            'data' => [
                'from' =>
                    $from->toDateString(),

                'to' =>
                    $to->toDateString(),

                'tasks' => $tasks,

                'without_due_date' =>
                    $withoutDueDate,
            ],

            'meta' => [
                'total' =>
                    $tasks->count(),
            ],
        ]);
    }
}
