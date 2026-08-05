<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\TaskStatus;
use App\Http\Requests\Task\ProjectTaskSummaryRequest;
use App\Models\Project;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ProjectTaskSummaryController
{
    public function __invoke(
        ProjectTaskSummaryRequest $request,
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

        $period = (string) $request
            ->validated('period');

        $query = $project
            ->tasks()
            ->getQuery();

        $this->applyPeriodFilter(
            $query,
            $period
        );

        $total = (clone $query)->count();

        $completed = (clone $query)
            ->where(
                'status',
                TaskStatus::Completed->value
            )
            ->count();

        $statusCounts = (clone $query)
            ->selectRaw(
                'status, COUNT(*) as aggregate'
            )
            ->groupBy('status')
            ->pluck(
                'aggregate',
                'status'
            );

        $remaining = max(
            $total - $completed,
            0
        );

        $completionPercentage =
            $total > 0
                ? (int) round(
                    ($completed / $total) * 100
                )
                : 0;

        $withoutDueDate = $project
            ->tasks()
            ->whereNull('due_at')
            ->count();

        return response()->json([
            'data' => [
                'period' => $period,

                'total' => $total,

                'completed' => $completed,

                'remaining' => $remaining,

                'completion_percentage' =>
                    $completionPercentage,

                'by_status' => [
                    'backlog' => (int)
                        $statusCounts->get(
                            TaskStatus::Backlog->value,
                            0
                        ),

                    'todo' => (int)
                        $statusCounts->get(
                            TaskStatus::Todo->value,
                            0
                        ),

                    'in_progress' => (int)
                        $statusCounts->get(
                            TaskStatus::InProgress->value,
                            0
                        ),

                    'in_review' => (int)
                        $statusCounts->get(
                            TaskStatus::InReview->value,
                            0
                        ),

                    'completed' => (int)
                        $statusCounts->get(
                            TaskStatus::Completed->value,
                            0
                        ),
                ],

                'without_due_date' =>
                    $withoutDueDate,
            ],
        ]);
    }

    private function applyPeriodFilter(
        Builder $query,
        string $period
    ): void {
        if ($period === 'all') {
            return;
        }

        $today = CarbonImmutable::today();

        if ($period === 'this_week') {
            $start = $today->startOfWeek(
                CarbonInterface::MONDAY
            );

            $end = $today->endOfWeek(
                CarbonInterface::SUNDAY
            );

            $query
                ->whereNotNull('due_at')
                ->whereBetween(
                    'due_at',
                    [
                        $start->startOfDay(),
                        $end->endOfDay(),
                    ]
                );

            return;
        }

        if ($period === 'this_month') {
            $query
                ->whereNotNull('due_at')
                ->whereBetween(
                    'due_at',
                    [
                        $today
                            ->startOfMonth()
                            ->startOfDay(),

                        $today
                            ->endOfMonth()
                            ->endOfDay(),
                    ]
                );

            return;
        }

        if ($period === 'this_year') {
            $query
                ->whereNotNull('due_at')
                ->whereBetween(
                    'due_at',
                    [
                        $today
                            ->startOfYear()
                            ->startOfDay(),

                        $today
                            ->endOfYear()
                            ->endOfDay(),
                    ]
                );
        }
    }
}
