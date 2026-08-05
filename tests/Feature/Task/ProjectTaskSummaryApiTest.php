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

class ProjectTaskSummaryApiTest extends TestCase
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
                    'Summary Workspace',

                'slug' =>
                    'summary-workspace',
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
                    'Summary Project',

                'slug' =>
                    'summary-project',

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

    public function test_it_returns_complete_project_task_summary(): void
    {
        $this->createTask(
            'Backlog task',
            TaskStatus::Backlog,
            null
        );

        $this->createTask(
            'Todo task',
            TaskStatus::Todo,
            '2026-08-10'
        );

        $this->createTask(
            'In progress task',
            TaskStatus::InProgress,
            '2026-08-11'
        );

        $this->createTask(
            'In review task',
            TaskStatus::InReview,
            null
        );

        $this->createTask(
            'Completed task one',
            TaskStatus::Completed,
            '2026-08-12'
        );

        $this->createTask(
            'Completed task two',
            TaskStatus::Completed,
            '2026-08-13'
        );

        $this->getJson(
            $this->summaryUrl()
            . '?period=all'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.period',
                'all'
            )
            ->assertJsonPath(
                'data.total',
                6
            )
            ->assertJsonPath(
                'data.completed',
                2
            )
            ->assertJsonPath(
                'data.remaining',
                4
            )
            ->assertJsonPath(
                'data.completion_percentage',
                33
            )
            ->assertJsonPath(
                'data.by_status.backlog',
                1
            )
            ->assertJsonPath(
                'data.by_status.todo',
                1
            )
            ->assertJsonPath(
                'data.by_status.in_progress',
                1
            )
            ->assertJsonPath(
                'data.by_status.in_review',
                1
            )
            ->assertJsonPath(
                'data.by_status.completed',
                2
            )
            ->assertJsonPath(
                'data.without_due_date',
                2
            );
    }

    public function test_it_filters_summary_by_current_week(): void
    {
        Carbon::setTestNow(
            '2026-08-05 10:00:00'
        );

        $this->createTask(
            'Completed this week',
            TaskStatus::Completed,
            '2026-08-04'
        );

        $this->createTask(
            'Todo this week',
            TaskStatus::Todo,
            '2026-08-07'
        );

        $this->createTask(
            'Next month task',
            TaskStatus::InProgress,
            '2026-09-01'
        );

        $this->createTask(
            'Task without due date',
            TaskStatus::Backlog,
            null
        );

        $this->getJson(
            $this->summaryUrl()
            . '?period=this_week'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.period',
                'this_week'
            )
            ->assertJsonPath(
                'data.total',
                2
            )
            ->assertJsonPath(
                'data.completed',
                1
            )
            ->assertJsonPath(
                'data.remaining',
                1
            )
            ->assertJsonPath(
                'data.completion_percentage',
                50
            )
            ->assertJsonPath(
                'data.by_status.todo',
                1
            )
            ->assertJsonPath(
                'data.by_status.completed',
                1
            )
            ->assertJsonPath(
                'data.without_due_date',
                1
            );
    }

    public function test_empty_period_returns_zero_summary(): void
    {
        Carbon::setTestNow(
            '2026-08-05 10:00:00'
        );

        $this->createTask(
            'Task outside current month',
            TaskStatus::Completed,
            '2026-09-10'
        );

        $this->getJson(
            $this->summaryUrl()
            . '?period=this_month'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.total',
                0
            )
            ->assertJsonPath(
                'data.completed',
                0
            )
            ->assertJsonPath(
                'data.remaining',
                0
            )
            ->assertJsonPath(
                'data.completion_percentage',
                0
            )
            ->assertJsonPath(
                'data.by_status.backlog',
                0
            )
            ->assertJsonPath(
                'data.by_status.todo',
                0
            )
            ->assertJsonPath(
                'data.by_status.in_progress',
                0
            )
            ->assertJsonPath(
                'data.by_status.in_review',
                0
            )
            ->assertJsonPath(
                'data.by_status.completed',
                0
            );
    }

    public function test_summary_period_is_validated(): void
    {
        $this->getJson(
            $this->summaryUrl()
            . '?period=invalid'
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'period',
            ]);
    }

    public function test_outsider_cannot_view_project_summary(): void
    {
        $outsider =
            User::factory()->create();

        Sanctum::actingAs($outsider);

        $this->getJson(
            $this->summaryUrl()
        )->assertForbidden();
    }

    public function test_project_from_another_workspace_returns_not_found(): void
    {
        $anotherOwner =
            User::factory()->create();

        $anotherWorkspace =
            Workspace::query()->create([
                'owner_id' =>
                    $anotherOwner->id,

                'name' =>
                    'Another Workspace',

                'slug' =>
                    'another-workspace',
            ]);

        $anotherProject =
            Project::query()->create([
                'workspace_id' =>
                    $anotherWorkspace->id,

                'created_by' =>
                    $anotherOwner->id,

                'name' =>
                    'Another Project',

                'slug' =>
                    'another-project',

                'status' =>
                    ProjectStatus::Active,
            ]);

        $this->getJson(
            sprintf(
                '/api/workspaces/%d/projects/%d/task-summary',
                $this->workspace->id,
                $anotherProject->id
            )
        )->assertNotFound();
    }

    private function createTask(
        string $title,
        TaskStatus $status,
        ?string $dueAt
    ): Task {
        return Task::query()->create([
            'project_id' =>
                $this->project->id,

            'created_by' =>
                $this->owner->id,

            'title' => $title,

            'description' => null,

            'status' => $status,

            'priority' =>
                TaskPriority::Medium,

            'position' =>
                Task::query()
                    ->where(
                        'project_id',
                        $this->project->id
                    )
                    ->count() + 1,

            'due_at' => $dueAt,

            'completed_at' =>
                $status->isCompleted()
                    ? now()
                    : null,
        ]);
    }

    private function summaryUrl(): string
    {
        return sprintf(
            '/api/workspaces/%d/projects/%d/task-summary',
            $this->workspace->id,
            $this->project->id
        );
    }
}
