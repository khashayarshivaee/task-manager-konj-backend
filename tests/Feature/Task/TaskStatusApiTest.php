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
use App\Models\ProjectActivity;

class TaskStatusApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_member_can_update_task_status(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $workspace = $this->createWorkspace(
            $owner
        );

        $this->addMember(
            $workspace,
            $member
        );

        $project = $this->createProject(
            $workspace,
            $owner
        );

        $task = $this->createTask(
            $project,
            $owner,
            TaskStatus::Todo
        );

        Sanctum::actingAs($member);

        $this->patchJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}/tasks/{$task->id}/status",
            [
                'status' =>
                    TaskStatus::InProgress->value,
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Task status updated successfully.'
            )
            ->assertJsonPath(
                'data.task.status',
                TaskStatus::InProgress->value
            )
            ->assertJsonPath(
                'data.task.completed_at',
                null
            );

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,

            'status' =>
                TaskStatus::InProgress->value,
        ]);

        $this->assertDatabaseHas(
            'task_watchers',
            [
                'task_id' => $task->id,
                'user_id' => $member->id,
            ]
        );
        $this->assertDatabaseHas(
            'project_activities',
            [
                'project_id' =>
                    $project->id,

                'actor_id' =>
                    $member->id,

                'type' =>
                    'task_status_changed',

                'subject_type' =>
                    'task',

                'subject_id' =>
                    $task->id,

                'subject_label' =>
                    $task->title,
            ]
        );
        $activity = ProjectActivity::query()
            ->where(
                'project_id',
                $project->id
            )
            ->where(
                'type',
                'task_status_changed'
            )
            ->where(
                'subject_id',
                $task->id
            )
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            TaskStatus::Todo->value,
            data_get(
                $activity->metadata,
                'from'
            )
        );

        $this->assertSame(
            TaskStatus::InProgress->value,
            data_get(
                $activity->metadata,
                'to'
            )
        );
        $this->assertDatabaseMissing(
            'project_activities',
            [
                'project_id' =>
                    $project->id,

                'type' =>
                    'task_updated',

                'subject_id' =>
                    $task->id,
            ]
        );
    }

    public function test_completing_task_sets_completed_timestamp(): void
    {
        $owner = User::factory()->create();

        $workspace = $this->createWorkspace(
            $owner
        );

        $project = $this->createProject(
            $workspace,
            $owner
        );

        $task = $this->createTask(
            $project,
            $owner,
            TaskStatus::Todo
        );

        Sanctum::actingAs($owner);

        $this->patchJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}/tasks/{$task->id}/status",
            [
                'status' =>
                    TaskStatus::Completed->value,
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.task.status',
                TaskStatus::Completed->value
            )
            ->assertJsonPath(
                'data.task.completed_at',
                fn ($value): bool =>
                    $value !== null
            );

        $task->refresh();

        $this->assertTrue(
            $task->status->isCompleted()
        );

        $this->assertNotNull(
            $task->completed_at
        );
    }

    public function test_reopening_completed_task_clears_completed_timestamp(): void
    {
        $owner = User::factory()->create();

        $workspace = $this->createWorkspace(
            $owner
        );

        $project = $this->createProject(
            $workspace,
            $owner
        );

        $task = $this->createTask(
            $project,
            $owner,
            TaskStatus::Completed,
            1,
            now()->subHour()->toDateTimeString()
        );

        Sanctum::actingAs($owner);

        $this->patchJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}/tasks/{$task->id}/status",
            [
                'status' =>
                    TaskStatus::Todo->value,
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.task.status',
                TaskStatus::Todo->value
            )
            ->assertJsonPath(
                'data.task.completed_at',
                null
            );

        $task->refresh();

        $this->assertSame(
            TaskStatus::Todo,
            $task->status
        );

        $this->assertNull(
            $task->completed_at
        );
    }

    public function test_status_change_moves_task_to_end_of_target_status(): void
    {
        $owner = User::factory()->create();

        $workspace = $this->createWorkspace(
            $owner
        );

        $project = $this->createProject(
            $workspace,
            $owner
        );

        $this->createTask(
            $project,
            $owner,
            TaskStatus::InProgress,
            1
        );

        $this->createTask(
            $project,
            $owner,
            TaskStatus::InProgress,
            2
        );

        $task = $this->createTask(
            $project,
            $owner,
            TaskStatus::Todo,
            8
        );

        Sanctum::actingAs($owner);

        $this->patchJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}/tasks/{$task->id}/status",
            [
                'status' =>
                    TaskStatus::InProgress->value,
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.task.position',
                3
            );

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,

            'status' =>
                TaskStatus::InProgress->value,

            'position' => 3,
        ]);
    }

    public function test_invalid_task_status_is_rejected(): void
    {
        $owner = User::factory()->create();

        $workspace = $this->createWorkspace(
            $owner
        );

        $project = $this->createProject(
            $workspace,
            $owner
        );

        $task = $this->createTask(
            $project,
            $owner,
            TaskStatus::Todo
        );

        Sanctum::actingAs($owner);

        $this->patchJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}/tasks/{$task->id}/status",
            [
                'status' => 'done',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
            ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,

            'status' =>
                TaskStatus::Todo->value,
        ]);
    }

    public function test_outsider_cannot_update_task_status(): void
    {
        $owner = User::factory()->create();

        $outsider =
            User::factory()->create();

        $workspace = $this->createWorkspace(
            $owner
        );

        $project = $this->createProject(
            $workspace,
            $owner
        );

        $task = $this->createTask(
            $project,
            $owner,
            TaskStatus::Todo
        );

        Sanctum::actingAs($outsider);

        $this->patchJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}/tasks/{$task->id}/status",
            [
                'status' =>
                    TaskStatus::Completed->value,
            ]
        )->assertForbidden();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,

            'status' =>
                TaskStatus::Todo->value,
        ]);
    }

    public function test_task_status_cannot_be_updated_through_another_project(): void
    {
        $owner = User::factory()->create();

        $workspace = $this->createWorkspace(
            $owner
        );

        $firstProject = $this->createProject(
            $workspace,
            $owner,
            'First Project',
            'first-project'
        );

        $secondProject = $this->createProject(
            $workspace,
            $owner,
            'Second Project',
            'second-project'
        );

        $task = $this->createTask(
            $secondProject,
            $owner,
            TaskStatus::Todo
        );

        Sanctum::actingAs($owner);

        $this->patchJson(
            "/api/workspaces/{$workspace->id}/projects/{$firstProject->id}/tasks/{$task->id}/status",
            [
                'status' =>
                    TaskStatus::Completed->value,
            ]
        )->assertNotFound();
    }

    private function createWorkspace(
        User $owner
    ): Workspace {
        $workspace =
            Workspace::query()->create([
                'owner_id' => $owner->id,
                'name' => 'Test Workspace',
                'slug' => 'test-workspace',
            ]);

        $workspace->memberships()->create([
            'user_id' => $owner->id,

            'role' =>
                WorkspaceRole::Owner,

            'joined_at' => now(),
        ]);

        return $workspace;
    }

    private function addMember(
        Workspace $workspace,
        User $user
    ): void {
        $workspace->memberships()->create([
            'user_id' => $user->id,

            'role' =>
                WorkspaceRole::Member,

            'joined_at' => now(),
        ]);
    }

    private function createProject(
        Workspace $workspace,
        User $creator,
        string $name = 'Test Project',
        string $slug = 'test-project'
    ): Project {
        return Project::query()->create([
            'workspace_id' =>
                $workspace->id,

            'created_by' =>
                $creator->id,

            'name' => $name,
            'slug' => $slug,

            'status' =>
                ProjectStatus::Active,
        ]);
    }

    private function createTask(
        Project $project,
        User $creator,
        TaskStatus $status,
        int $position = 1,
        ?string $completedAt = null
    ): Task {
        return Task::query()->create([
            'project_id' =>
                $project->id,

            'created_by' =>
                $creator->id,

            'assigned_to' => null,
            'title' => 'Status Task',
            'description' => null,
            'status' => $status,

            'priority' =>
                TaskPriority::Medium,

            'starts_at' => null,
            'due_at' => null,

            'completed_at' =>
                $completedAt,

            'position' => $position,
        ]);
    }
}
