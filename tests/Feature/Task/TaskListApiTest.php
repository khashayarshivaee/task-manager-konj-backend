<?php

declare(strict_types=1);

namespace Tests\Feature\Task;

use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskListApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner =
            User::factory()->create();

        $this->workspace =
            Workspace::query()->create([
                'owner_id' =>
                    $this->owner->id,

                'name' =>
                    'Task List Workspace',

                'slug' =>
                    'task-list-workspace',
            ]);

        $this->workspace
            ->memberships()
            ->create([
                'user_id' =>
                    $this->owner->id,

                'role' =>
                    WorkspaceRole::Owner,

                'joined_at' => now(),
            ]);

        $this->project =
            Project::query()->create([
                'workspace_id' =>
                    $this->workspace->id,

                'created_by' =>
                    $this->owner->id,

                'name' =>
                    'Task List Project',

                'slug' =>
                    'task-list-project',

                'status' =>
                    ProjectStatus::Active,
            ]);

        Sanctum::actingAs(
            $this->owner
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_task_list_is_paginated(): void
    {
        for (
            $index = 1;
            $index <= 25;
            $index++
        ) {
            $this->createTask(
                sprintf(
                    'Task %02d',
                    $index
                ),
                TaskStatus::Todo,
                TaskPriority::Medium,
                $index
            );
        }

        $this->getJson(
            $this->tasksUrl()
            . '?page=2'
            . '&per_page=10'
            . '&sort=title'
            . '&direction=asc'
        )
            ->assertOk()
            ->assertJsonCount(
                10,
                'data.tasks'
            )
            ->assertJsonPath(
                'data.tasks.0.title',
                'Task 11'
            )
            ->assertJsonPath(
                'meta.current_page',
                2
            )
            ->assertJsonPath(
                'meta.per_page',
                10
            )
            ->assertJsonPath(
                'meta.total',
                25
            )
            ->assertJsonPath(
                'meta.last_page',
                3
            )
            ->assertJsonPath(
                'meta.from',
                11
            )
            ->assertJsonPath(
                'meta.to',
                20
            )
            ->assertJsonPath(
                'meta.has_more_pages',
                true
            );
    }

    public function test_task_list_can_be_filtered_by_search_status_and_priority(): void
    {
        $this->createTask(
            'Build pagination API',
            TaskStatus::InProgress,
            TaskPriority::Urgent,
            1,
            'Implement task pagination.'
        );

        $this->createTask(
            'Build another API',
            TaskStatus::InProgress,
            TaskPriority::High,
            2
        );

        $this->createTask(
            'Pagination research',
            TaskStatus::Todo,
            TaskPriority::Urgent,
            3
        );

        $this->getJson(
            $this->tasksUrl()
            . '?search=pagination'
            . '&status=in_progress'
            . '&priority=urgent'
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.tasks'
            )
            ->assertJsonPath(
                'data.tasks.0.title',
                'Build pagination API'
            )
            ->assertJsonPath(
                'meta.total',
                1
            );
    }

    public function test_task_list_can_be_filtered_by_current_assignee(): void
    {
        $member =
            User::factory()->create();

        $this->workspace
            ->memberships()
            ->create([
                'user_id' =>
                    $member->id,

                'role' =>
                    WorkspaceRole::Member,

                'joined_at' => now(),
            ]);

        $assignedTask =
            $this->createTask(
                'Assigned to member',
                TaskStatus::Todo,
                TaskPriority::Medium,
                1
            );

        $assignedTask
            ->assignees()
            ->attach(
                $member->id,
                [
                    'assigned_by' =>
                        $this->owner->id,
                ]
            );

        $ownerTask =
            $this->createTask(
                'Assigned to owner',
                TaskStatus::Todo,
                TaskPriority::Medium,
                2
            );

        $ownerTask
            ->assignees()
            ->attach(
                $this->owner->id,
                [
                    'assigned_by' =>
                        $this->owner->id,
                ]
            );

        Sanctum::actingAs($member);

        $this->getJson(
            $this->tasksUrl()
            . '?assignee=me'
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.tasks'
            )
            ->assertJsonPath(
                'data.tasks.0.id',
                $assignedTask->id
            );
    }

    public function test_task_list_can_be_filtered_by_due_range_and_overdue(): void
    {
        Carbon::setTestNow(
            '2026-08-05 10:00:00'
        );

        $this->createTask(
            'Overdue open task',
            TaskStatus::Todo,
            TaskPriority::High,
            1,
            null,
            '2026-08-01'
        );

        $this->createTask(
            'Completed old task',
            TaskStatus::Completed,
            TaskPriority::Medium,
            2,
            null,
            '2026-08-01'
        );

        $this->createTask(
            'This week task',
            TaskStatus::InProgress,
            TaskPriority::Medium,
            3,
            null,
            '2026-08-07'
        );

        $this->createTask(
            'No due date task',
            TaskStatus::Backlog,
            TaskPriority::Low,
            4
        );

        $this->getJson(
            $this->tasksUrl()
            . '?due=overdue'
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.tasks'
            )
            ->assertJsonPath(
                'data.tasks.0.title',
                'Overdue open task'
            );

        $this->getJson(
            $this->tasksUrl()
            . '?due=this_week'
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.tasks'
            )
            ->assertJsonPath(
                'data.tasks.0.title',
                'This week task'
            );

        $this->getJson(
            $this->tasksUrl()
            . '?due=no_due_date'
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.tasks'
            )
            ->assertJsonPath(
                'data.tasks.0.title',
                'No due date task'
            );
    }

    public function test_task_list_filters_are_validated(): void
    {
        $outsider =
            User::factory()->create();

        $this->getJson(
            $this->tasksUrl()
            . '?page=0'
            . '&per_page=101'
            . '&status=invalid'
            . '&priority=invalid'
            . '&assignee='
            . $outsider->id
            . '&due=invalid'
            . '&sort=invalid'
            . '&direction=sideways'
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'page',
                'per_page',
                'status',
                'priority',
                'assignee',
                'due',
                'sort',
                'direction',
            ]);
    }

    private function createTask(
        string $title,
        TaskStatus $status,
        TaskPriority $priority,
        int $position,
        ?string $description = null,
        ?string $dueAt = null
    ): Task {
        return Task::query()->create([
            'project_id' =>
                $this->project->id,

            'created_by' =>
                $this->owner->id,

            'title' => $title,

            'description' =>
                $description,

            'status' => $status,

            'priority' => $priority,

            'position' => $position,

            'due_at' => $dueAt,

            'completed_at' =>
                $status->isCompleted()
                    ? now()
                    : null,
        ]);
    }

    private function tasksUrl(): string
    {
        return sprintf(
            '/api/workspaces/%d/projects/%d/tasks',
            $this->workspace->id,
            $this->project->id
        );
    }
}
