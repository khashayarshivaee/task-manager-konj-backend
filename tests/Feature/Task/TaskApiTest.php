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
use App\Models\TaskComment;
class TaskApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_member_can_list_project_tasks(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $workspace = $this->createWorkspace($owner);

        $this->addMember(
            $workspace,
            $member
        );

        $project = $this->createProject(
            $workspace,
            $owner
        );

        Task::query()->create([
            'project_id' => $project->id,
            'created_by' => $owner->id,
            'assigned_to' => $member->id,
            'title' => 'First Task',
            'status' => TaskStatus::Todo,
            'priority' => TaskPriority::High,
            'position' => 1,
        ]);

        Sanctum::actingAs($member);

        $this->getJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}/tasks"
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.tasks'
            )
            ->assertJsonPath(
                'data.tasks.0.title',
                'First Task'
            )
            ->assertJsonPath(
                'data.tasks.0.assignee.id',
                $member->id
            );
    }

    public function test_workspace_member_can_create_task(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $workspace = $this->createWorkspace($owner);

        $this->addMember(
            $workspace,
            $member
        );

        $project = $this->createProject(
            $workspace,
            $owner
        );
        $project
            ->memberships()
            ->create([
                'user_id' => $member->id,
                'added_by' => $owner->id,
                'joined_at' => now(),
            ]);

        Sanctum::actingAs($member);

        $this->postJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}/tasks",
            [
                'title' => 'Build task modal',
                'description' => 'Create the task form.',
                'status' => TaskStatus::Todo->value,
                'priority' => TaskPriority::High->value,
                'assigned_to' => $member->id,
                'starts_at' => '2026-08-03',
                'due_at' => '2026-08-10',
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Task created successfully.'
            )
            ->assertJsonPath(
                'data.task.title',
                'Build task modal'
            )
            ->assertJsonPath(
                'data.task.status',
                TaskStatus::Todo->value
            )
            ->assertJsonPath(
                'data.task.priority',
                TaskPriority::High->value
            )
            ->assertJsonPath(
                'data.task.position',
                1
            );

        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'created_by' => $member->id,
            'assigned_to' => $member->id,
            'title' => 'Build task modal',
        ]);
    }
    public function test_workspace_member_cannot_assign_user_who_is_not_project_member(): void
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

        Sanctum::actingAs($owner);

        $this->postJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}/tasks",
            [
                'title' => 'Restricted assignment',

                'assignee_ids' => [
                    $member->id,
                ],
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'assignee_ids.0',
            ]);

        $this->assertDatabaseMissing(
            'tasks',
            [
                'project_id' => $project->id,
                'title' => 'Restricted assignment',
            ]
        );
    }

    public function test_task_assignee_must_belong_to_workspace(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();

        $workspace = $this->createWorkspace($owner);

        $project = $this->createProject(
            $workspace,
            $owner
        );

        Sanctum::actingAs($owner);

        $this->postJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}/tasks",
            [
                'title' => 'Invalid assignee',
                'assigned_to' => $outsider->id,
            ]
        )
         ->assertUnprocessable()
         ->assertJsonValidationErrors([
             'assignee_ids.0',
         ]);

        $this->assertDatabaseCount(
            'tasks',
            0
        );
    }

    public function test_outsider_cannot_list_project_tasks(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();

        $workspace = $this->createWorkspace($owner);

        $project = $this->createProject(
            $workspace,
            $owner
        );

        Sanctum::actingAs($outsider);

        $this->getJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}/tasks"
        )->assertForbidden();
    }

    public function test_project_cannot_be_used_through_another_workspace(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();

        $firstWorkspace = $this->createWorkspace(
            $firstOwner,
            'First Workspace',
            'first-workspace'
        );

        $secondWorkspace = $this->createWorkspace(
            $secondOwner,
            'Second Workspace',
            'second-workspace'
        );

        $project = $this->createProject(
            $secondWorkspace,
            $secondOwner
        );

        Sanctum::actingAs($firstOwner);

        $this->getJson(
            "/api/workspaces/{$firstWorkspace->id}/projects/{$project->id}/tasks"
        )->assertNotFound();
    }

    public function test_completed_task_receives_completed_timestamp(): void
    {
        $owner = User::factory()->create();

        $workspace = $this->createWorkspace($owner);

        $project = $this->createProject(
            $workspace,
            $owner
        );

        Sanctum::actingAs($owner);

        $this->postJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}/tasks",
            [
                'title' => 'Completed Task',
                'status' => TaskStatus::Completed->value,
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.task.status',
                TaskStatus::Completed->value
            )
            ->assertJsonPath(
                'data.task.completed_at',
                fn ($value) => $value !== null
            );
    }

    private function createWorkspace(
        User $owner,
        string $name = 'Test Workspace',
        string $slug = 'test-workspace'
    ): Workspace {
        $workspace = Workspace::query()->create([
            'owner_id' => $owner->id,
            'name' => $name,
            'slug' => $slug,
        ]);

        $workspace->memberships()->create([
            'user_id' => $owner->id,
            'role' => WorkspaceRole::Owner,
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
            'role' => WorkspaceRole::Member,
            'joined_at' => now(),
        ]);
    }

    public function test_workspace_member_can_view_task(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $workspace = $this->createWorkspace($owner);

        $this->addMember(
            $workspace,
            $member
        );

        $project = $this->createProject(
            $workspace,
            $owner
        );

        $task = Task::query()->create([
            'project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Visible Task',
            'status' => TaskStatus::Todo,
            'priority' => TaskPriority::Medium,
            'position' => 1,
        ]);

        Sanctum::actingAs($member);

        $this->getJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}/tasks/{$task->id}"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.task.id',
                $task->id
            )
            ->assertJsonPath(
                'data.task.title',
                'Visible Task'
            );
    }

    public function test_task_show_returns_current_users_unread_comment_count(): void
    {
        $owner =
            User::factory()->create();

        $member =
            User::factory()->create();

        $workspace =
            $this->createWorkspace(
                $owner,
            );

        $this->addMember(
            $workspace,
            $member,
        );

        $project =
            $this->createProject(
                $workspace,
                $owner,
            );

        $task = Task::query()->create([
            'project_id' => $project->id,
            'created_by' => $owner->id,
            'title' =>
                'Task details with unread comment',
            'status' => TaskStatus::Todo,
            'priority' => TaskPriority::Medium,
            'position' => 1,
        ]);

        $task
            ->watchers()
            ->attach(
                $member->id,
                [
                    'last_read_comment_id' =>
                        null,
                ],
            );

        TaskComment::query()->create([
            'task_id' => $task->id,
            'user_id' => $owner->id,
            'parent_id' => null,
            'body' =>
                'Unread comment in task details.',
        ]);

        Sanctum::actingAs(
            $member,
        );

        $taskUrl =
            "/api/workspaces/{$workspace->id}"
            ."/projects/{$project->id}"
            ."/tasks/{$task->id}";

        $this->getJson(
            $taskUrl,
        )
            ->assertOk()
            ->assertJsonPath(
                'data.task.id',
                $task->id,
            )
            ->assertJsonPath(
                'data.task.unread_comments_count',
                1,
            );

        $this->patchJson(
            $taskUrl.'/comments/read',
        )
            ->assertOk()
            ->assertJsonPath(
                'data.unread_comments_count',
                0,
            );

        $this->getJson(
            $taskUrl,
        )
            ->assertOk()
            ->assertJsonPath(
                'data.task.unread_comments_count',
                0,
            );
    }

    public function test_workspace_member_can_update_task(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $workspace = $this->createWorkspace($owner);

        $this->addMember(
            $workspace,
            $member
        );

        $project = $this->createProject(
            $workspace,
            $owner
        );
        $project
            ->memberships()
            ->create([
                'user_id' => $member->id,
                'added_by' => $owner->id,
                'joined_at' => now(),
            ]);

        $task = Task::query()->create([
            'project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Old Task',
            'status' => TaskStatus::Todo,
            'priority' => TaskPriority::Low,
            'position' => 1,
        ]);

        Sanctum::actingAs($member);

        $this->putJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}/tasks/{$task->id}",
            [
                'title' => 'Updated Task',
                'description' => 'Updated description.',
                'status' => TaskStatus::InProgress->value,
                'priority' => TaskPriority::High->value,
                'assigned_to' => $member->id,
                'starts_at' => '2026-08-03',
                'due_at' => '2026-08-10',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Task updated successfully.'
            )
            ->assertJsonPath(
                'data.task.title',
                'Updated Task'
            )
            ->assertJsonPath(
                'data.task.status',
                TaskStatus::InProgress->value
            )
            ->assertJsonPath(
                'data.task.priority',
                TaskPriority::High->value
            );

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Updated Task',
            'assigned_to' => $member->id,
            'status' => TaskStatus::InProgress->value,
        ]);
    }

    public function test_completed_timestamp_is_managed_when_status_changes(): void
    {
        $owner = User::factory()->create();

        $workspace = $this->createWorkspace($owner);

        $project = $this->createProject(
            $workspace,
            $owner
        );

        $task = Task::query()->create([
            'project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Status Task',
            'status' => TaskStatus::Todo,
            'priority' => TaskPriority::Medium,
            'position' => 1,
        ]);

        Sanctum::actingAs($owner);

        $this->putJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}/tasks/{$task->id}",
            [
                'title' => 'Status Task',
                'description' => null,
                'status' => TaskStatus::Completed->value,
                'priority' => TaskPriority::Medium->value,
                'assigned_to' => null,
                'starts_at' => null,
                'due_at' => null,
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.task.completed_at',
                fn ($value) => $value !== null
            );

        $this->putJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}/tasks/{$task->id}",
            [
                'title' => 'Status Task',
                'description' => null,
                'status' => TaskStatus::InProgress->value,
                'priority' => TaskPriority::Medium->value,
                'assigned_to' => null,
                'starts_at' => null,
                'due_at' => null,
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.task.completed_at',
                null
            );
    }

    public function test_task_creator_can_delete_task(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $workspace = $this->createWorkspace($owner);

        $this->addMember(
            $workspace,
            $member
        );

        $project = $this->createProject(
            $workspace,
            $owner
        );

        $task = Task::query()->create([
            'project_id' => $project->id,
            'created_by' => $member->id,
            'title' => 'Creator Task',
            'status' => TaskStatus::Backlog,
            'priority' => TaskPriority::Medium,
            'position' => 1,
        ]);

        Sanctum::actingAs($member);

        $this->deleteJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}/tasks/{$task->id}"
        )->assertOk();

        $this->assertDatabaseMissing('tasks', [
            'id' => $task->id,
        ]);
    }

    public function test_unrelated_member_cannot_delete_task(): void
    {
        $owner = User::factory()->create();
        $creator = User::factory()->create();
        $otherMember = User::factory()->create();

        $workspace = $this->createWorkspace($owner);

        $this->addMember(
            $workspace,
            $creator
        );

        $this->addMember(
            $workspace,
            $otherMember
        );

        $project = $this->createProject(
            $workspace,
            $owner
        );

        $task = Task::query()->create([
            'project_id' => $project->id,
            'created_by' => $creator->id,
            'title' => 'Protected Task',
            'status' => TaskStatus::Backlog,
            'priority' => TaskPriority::Medium,
            'position' => 1,
        ]);

        Sanctum::actingAs($otherMember);

        $this->deleteJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}/tasks/{$task->id}"
        )->assertForbidden();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
        ]);
    }

    public function test_task_cannot_be_accessed_through_another_project(): void
    {
        $owner = User::factory()->create();

        $workspace = $this->createWorkspace($owner);

        $firstProject = $this->createProject(
            $workspace,
            $owner
        );

        $secondProject = Project::query()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
            'name' => 'Second Project',
            'slug' => 'second-project',
            'status' => ProjectStatus::Active,
        ]);

        $task = Task::query()->create([
            'project_id' => $secondProject->id,
            'created_by' => $owner->id,
            'title' => 'Second Project Task',
            'status' => TaskStatus::Backlog,
            'priority' => TaskPriority::Medium,
            'position' => 1,
        ]);

        Sanctum::actingAs($owner);

        $this->getJson(
            "/api/workspaces/{$workspace->id}/projects/{$firstProject->id}/tasks/{$task->id}"
        )->assertNotFound();
    }

    private function createProject(
        Workspace $workspace,
        User $creator
    ): Project {
        return Project::query()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $creator->id,
            'name' => 'Test Project',
            'slug' => 'test-project',
            'status' => ProjectStatus::Active,
        ]);
    }
}
