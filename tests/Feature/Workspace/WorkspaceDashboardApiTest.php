<?php

declare(strict_types=1);

namespace Tests\Feature\Workspace;

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

class WorkspaceDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_real_workspace_statistics(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();

        $workspace = $this->createWorkspace(
            $owner,
            'Konj Workspace',
            'konj-workspace',
        );

        $workspace->memberships()->create([
            'user_id' => $admin->id,

            'role' =>
                WorkspaceRole::Admin->value,

            'joined_at' => now(),
        ]);

        $workspace->memberships()->create([
            'user_id' => $member->id,

            'role' =>
                WorkspaceRole::Member->value,

            'joined_at' => now(),
        ]);

        $project = $this->createProject(
            $workspace,
            $owner,
            'Task Manager',
            'task-manager',
        );

        $overdueTask = $this->createTask(
            $project,
            $owner,
            'Overdue task',
            TaskStatus::Todo,
            now()->subDay()->toDateString(),
        );

        $activeTask = $this->createTask(
            $project,
            $owner,
            'Active task',
            TaskStatus::InProgress,
            now()->addWeek()->toDateString(),
        );

        $this->createTask(
            $project,
            $owner,
            'Completed task',
            TaskStatus::Completed,
            now()->subWeek()->toDateString(),
        );

        $overdueTask
            ->assignees()
            ->attach(
                $owner->id,
                [
                    'assigned_by' =>
                        $owner->id,
                ],
            );

        $activeTask
            ->assignees()
            ->attach(
                $owner->id,
                [
                    'assigned_by' =>
                        $owner->id,
                ],
            );

        /*
        |--------------------------------------------------------------------------
        | Data from another workspace must not be counted.
        |--------------------------------------------------------------------------
        */

        $otherOwner =
            User::factory()->create();

        $otherWorkspace =
            $this->createWorkspace(
                $otherOwner,
                'Other Workspace',
                'other-workspace',
            );

        $otherProject =
            $this->createProject(
                $otherWorkspace,
                $otherOwner,
                'Other Project',
                'other-project',
            );

        $this->createTask(
            $otherProject,
            $otherOwner,
            'Other workspace task',
            TaskStatus::Todo,
            null,
        );

        Sanctum::actingAs($owner);

        $this->getJson(
            "/api/workspaces/{$workspace->id}/dashboard",
        )
            ->assertOk()

            ->assertJsonPath(
                'data.workspace.id',
                $workspace->id,
            )

            ->assertJsonPath(
                'data.setup.completed_steps',
                4,
            )

            ->assertJsonPath(
                'data.setup.total_steps',
                4,
            )

            ->assertJsonPath(
                'data.setup.completion_percentage',
                100,
            )

            ->assertJsonPath(
                'data.setup.steps.account_created',
                true,
            )

            ->assertJsonPath(
                'data.setup.steps.workspace_created',
                true,
            )

            ->assertJsonPath(
                'data.setup.steps.team_member_invited',
                true,
            )

            ->assertJsonPath(
                'data.setup.steps.project_created',
                true,
            )

            ->assertJsonPath(
                'data.projects.total',
                1,
            )

            ->assertJsonPath(
                'data.members.total',
                3,
            )

            ->assertJsonPath(
                'data.members.owners',
                1,
            )

            ->assertJsonPath(
                'data.members.admins',
                1,
            )

            ->assertJsonPath(
                'data.members.members',
                1,
            )

            ->assertJsonPath(
                'data.tasks.total',
                3,
            )

            ->assertJsonPath(
                'data.tasks.open',
                2,
            )

            ->assertJsonPath(
                'data.tasks.completed',
                1,
            )

            ->assertJsonPath(
                'data.tasks.assigned_to_me',
                2,
            )

            ->assertJsonPath(
                'data.tasks.overdue',
                1,
            );
    }

    public function test_new_workspace_has_two_completed_setup_steps(): void
    {
        $owner =
            User::factory()->create();

        $workspace =
            $this->createWorkspace(
                $owner,
                'Empty Workspace',
                'empty-workspace',
            );

        Sanctum::actingAs($owner);

        $this->getJson(
            "/api/workspaces/{$workspace->id}/dashboard",
        )
            ->assertOk()

            ->assertJsonPath(
                'data.setup.completed_steps',
                2,
            )

            ->assertJsonPath(
                'data.setup.completion_percentage',
                50,
            )

            ->assertJsonPath(
                'data.setup.steps.account_created',
                true,
            )

            ->assertJsonPath(
                'data.setup.steps.workspace_created',
                true,
            )

            ->assertJsonPath(
                'data.setup.steps.team_member_invited',
                false,
            )

            ->assertJsonPath(
                'data.setup.steps.project_created',
                false,
            )

            ->assertJsonPath(
                'data.projects.total',
                0,
            )

            ->assertJsonPath(
                'data.members.total',
                1,
            )

            ->assertJsonPath(
                'data.tasks.open',
                0,
            );
    }

    public function test_workspace_member_can_view_dashboard(): void
    {
        $owner =
            User::factory()->create();

        $member =
            User::factory()->create();

        $workspace =
            $this->createWorkspace(
                $owner,
                'Member Workspace',
                'member-workspace',
            );

        $workspace->memberships()->create([
            'user_id' => $member->id,

            'role' =>
                WorkspaceRole::Member->value,

            'joined_at' => now(),
        ]);

        Sanctum::actingAs($member);

        $this->getJson(
            "/api/workspaces/{$workspace->id}/dashboard",
        )->assertOk();
    }

    public function test_outsider_cannot_view_workspace_dashboard(): void
    {
        $owner =
            User::factory()->create();

        $outsider =
            User::factory()->create();

        $workspace =
            $this->createWorkspace(
                $owner,
                'Private Workspace',
                'private-workspace',
            );

        Sanctum::actingAs($outsider);

        $this->getJson(
            "/api/workspaces/{$workspace->id}/dashboard",
        )->assertForbidden();
    }

    public function test_guest_cannot_view_workspace_dashboard(): void
    {
        $owner =
            User::factory()->create();

        $workspace =
            $this->createWorkspace(
                $owner,
                'Guest Workspace',
                'guest-workspace',
            );

        $this->getJson(
            "/api/workspaces/{$workspace->id}/dashboard",
        )->assertUnauthorized();
    }

    private function createWorkspace(
        User $owner,
        string $name,
        string $slug,
    ): Workspace {
        $workspace =
            Workspace::query()->create([
                'owner_id' => $owner->id,
                'name' => $name,
                'slug' => $slug,
            ]);

        $workspace->memberships()->create([
            'user_id' => $owner->id,

            'role' =>
                WorkspaceRole::Owner->value,

            'joined_at' => now(),
        ]);

        return $workspace;
    }

    private function createProject(
        Workspace $workspace,
        User $creator,
        string $name,
        string $slug,
    ): Project {
        return Project::query()->create([
            'workspace_id' =>
                $workspace->id,

            'created_by' =>
                $creator->id,

            'name' => $name,
            'slug' => $slug,

            'status' =>
                ProjectStatus::Active->value,
        ]);
    }

    private function createTask(
        Project $project,
        User $creator,
        string $title,
        TaskStatus $status,
        ?string $dueAt,
    ): Task {
        return Task::query()->create([
            'project_id' =>
                $project->id,

            'created_by' =>
                $creator->id,

            'assigned_to' => null,
            'title' => $title,
            'description' => null,

            'status' =>
                $status->value,

            'priority' =>
                TaskPriority::Medium->value,

            'starts_at' => null,
            'due_at' => $dueAt,

            'completed_at' =>
                $status ===
                TaskStatus::Completed
                    ? now()
                    : null,

            'position' => 1,
        ]);
    }
}
