<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\TaskStatus;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WorkspaceDashboardController extends Controller
{
    /**
     * Return real statistics for the
     * selected workspace dashboard.
     */
    public function show(
        Request $request,
        Workspace $workspace,
    ): JsonResponse {
        Gate::authorize(
            'view',
            $workspace,
        );

        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
        );

        $roleCounts = $workspace
            ->memberships()
            ->selectRaw(
                'role, COUNT(*) as aggregate',
            )
            ->groupBy('role')
            ->pluck(
                'aggregate',
                'role',
            );

        $memberTotal = (int) $workspace
            ->memberships()
            ->count();

        $projectTotal = (int) $workspace
            ->projects()
            ->count();

        $taskQuery = Task::query()
            ->whereHas(
                'project',
                function (
                    Builder $query,
                ) use ($workspace): void {
                    $query->where(
                        'workspace_id',
                        $workspace->id,
                    );
                },
            );

        $taskTotal = (int) (
            clone $taskQuery
        )->count();

        $completedTasks = (int) (
            clone $taskQuery
        )
            ->where(
                'status',
                TaskStatus::Completed->value,
            )
            ->count();

        $openTasks = (int) (
            clone $taskQuery
        )
            ->where(
                'status',
                '!=',
                TaskStatus::Completed->value,
            )
            ->count();

        $assignedToMe = (int) (
            clone $taskQuery
        )
            ->whereHas(
                'assignees',
                function (
                    Builder $query,
                ) use ($user): void {
                    $query->where(
                        'users.id',
                        $user->id,
                    );
                },
            )
            ->count();

        $overdueTasks = (int) (
            clone $taskQuery
        )
            ->where(
                'status',
                '!=',
                TaskStatus::Completed->value,
            )
            ->whereNotNull('due_at')
            ->whereDate(
                'due_at',
                '<',
                today(),
            )
            ->count();

        /*
        |--------------------------------------------------------------------------
        | Getting started
        |--------------------------------------------------------------------------
        |
        | Account and workspace are already available
        | when this endpoint is requested.
        |
        */

        $setupSteps = [
            'account_created' => true,
            'workspace_created' => true,

            'team_member_invited' =>
                $memberTotal > 1,

            'project_created' =>
                $projectTotal > 0,
        ];

        $completedSetupSteps = count(
            array_filter($setupSteps),
        );

        $setupStepTotal = count(
            $setupSteps,
        );

        return response()->json([
            'data' => [
                'workspace' => [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'slug' => $workspace->slug,
                ],

                'setup' => [
                    'completed_steps' =>
                        $completedSetupSteps,

                    'total_steps' =>
                        $setupStepTotal,

                    'completion_percentage' =>
                        (int) round(
                            (
                                $completedSetupSteps /
                                $setupStepTotal
                            ) * 100,
                        ),

                    'steps' => $setupSteps,
                ],

                'projects' => [
                    'total' => $projectTotal,
                ],

                'members' => [
                    'total' => $memberTotal,

                    'owners' => (int) (
                        $roleCounts[
                            WorkspaceRole::Owner->value
                        ] ?? 0
                    ),

                    'admins' => (int) (
                        $roleCounts[
                            WorkspaceRole::Admin->value
                        ] ?? 0
                    ),

                    'members' => (int) (
                        $roleCounts[
                            WorkspaceRole::Member->value
                        ] ?? 0
                    ),
                ],

                'tasks' => [
                    'total' => $taskTotal,
                    'open' => $openTasks,
                    'completed' => $completedTasks,

                    'assigned_to_me' =>
                        $assignedToMe,

                    'overdue' =>
                        $overdueTasks,
                ],
            ],
        ]);
    }
}
