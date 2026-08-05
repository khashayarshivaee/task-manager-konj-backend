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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectTaskCalendarApiTest extends TestCase
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
                    'Calendar Workspace',

                'slug' =>
                    'calendar-workspace',
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
                    'Calendar Project',

                'slug' =>
                    'calendar-project',

                'status' =>
                    ProjectStatus::Active,
            ]);

        Sanctum::actingAs(
            $this->owner
        );
    }

    public function test_it_returns_tasks_inside_calendar_range(): void
    {
        $firstTask = $this->createTask(
            'First calendar task',
            TaskStatus::Todo,
            '2026-08-03',
            1
        );

        $secondTask = $this->createTask(
            'Second calendar task',
            TaskStatus::InProgress,
            '2026-08-18',
            2
        );

        $this->createTask(
            'Task outside range',
            TaskStatus::Todo,
            '2026-10-01',
            3
        );

        $this->createTask(
            'Task without due date',
            TaskStatus::Backlog,
            null,
            4
        );

        $this->getJson(
            $this->calendarUrl()
            . '?from=2026-07-27'
            . '&to=2026-09-06'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.from',
                '2026-07-27'
            )
            ->assertJsonPath(
                'data.to',
                '2026-09-06'
            )
            ->assertJsonCount(
                2,
                'data.tasks'
            )
            ->assertJsonPath(
                'data.tasks.0.id',
                $firstTask->id
            )
            ->assertJsonPath(
                'data.tasks.1.id',
                $secondTask->id
            )
            ->assertJsonPath(
                'data.without_due_date',
                1
            )
            ->assertJsonPath(
                'meta.total',
                2
            );
    }

    public function test_calendar_excludes_tasks_outside_requested_dates(): void
    {
        $this->createTask(
            'July task',
            TaskStatus::Todo,
            '2026-07-31',
            1
        );

        $augustTask = $this->createTask(
            'August task',
            TaskStatus::InReview,
            '2026-08-15',
            2
        );

        $this->createTask(
            'September task',
            TaskStatus::Completed,
            '2026-09-01',
            3
        );

        $this->getJson(
            $this->calendarUrl()
            . '?from=2026-08-01'
            . '&to=2026-08-31'
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.tasks'
            )
            ->assertJsonPath(
                'data.tasks.0.id',
                $augustTask->id
            );
    }

    public function test_calendar_dates_are_required_and_validated(): void
    {
        $this->getJson(
            $this->calendarUrl()
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'from',
                'to',
            ]);

        $this->getJson(
            $this->calendarUrl()
            . '?from=invalid'
            . '&to=2026-08-31'
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'from',
            ]);

        $this->getJson(
            $this->calendarUrl()
            . '?from=2026-08-20'
            . '&to=2026-08-01'
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'to',
            ]);
    }

    public function test_calendar_range_cannot_exceed_sixty_two_days(): void
    {
        $this->getJson(
            $this->calendarUrl()
            . '?from=2026-01-01'
            . '&to=2026-12-31'
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'to',
            ]);
    }

    public function test_outsider_cannot_view_project_calendar(): void
    {
        $outsider =
            User::factory()->create();

        Sanctum::actingAs($outsider);

        $this->getJson(
            $this->calendarUrl()
            . '?from=2026-08-01'
            . '&to=2026-08-31'
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
                    'another-calendar-workspace',
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
                    'another-calendar-project',

                'status' =>
                    ProjectStatus::Active,
            ]);

        $this->getJson(
            sprintf(
                '/api/workspaces/%d/projects/%d/tasks/calendar?from=2026-08-01&to=2026-08-31',
                $this->workspace->id,
                $anotherProject->id
            )
        )->assertNotFound();
    }

    private function createTask(
        string $title,
        TaskStatus $status,
        ?string $dueAt,
        int $position
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

            'position' => $position,

            'due_at' => $dueAt,

            'completed_at' =>
                $status->isCompleted()
                    ? now()
                    : null,
        ]);
    }

    private function calendarUrl(): string
    {
        return sprintf(
            '/api/workspaces/%d/projects/%d/tasks/calendar',
            $this->workspace->id,
            $this->project->id
        );
    }
}
