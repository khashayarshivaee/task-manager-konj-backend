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

class WorkspaceTaskApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_member_can_list_tasks_from_all_workspace_projects(): void
    {
        $owner =
            User::factory()->create();

        $member =
            User::factory()->create();

        $workspace =
            $this->createWorkspace(
                $owner
            );

        $this->addMember(
            $workspace,
            $member
        );

        $firstProject =
            $this->createProject(
                $workspace,
                $owner,
                'First Project',
                'first-project'
            );

        $secondProject =
            $this->createProject(
                $workspace,
                $owner,
                'Second Project',
                'second-project'
            );

        $firstTask =
            $this->createTask(
                $firstProject,
                $owner,
                'First Project Task'
            );

        $secondTask =
            $this->createTask(
                $secondProject,
                $owner,
                'Second Project Task'
            );

        $otherOwner =
            User::factory()->create();

        $otherWorkspace =
            $this->createWorkspace(
                $otherOwner,
                'Other Workspace',
                'other-workspace'
            );

        $otherProject =
            $this->createProject(
                $otherWorkspace,
                $otherOwner,
                'Other Project',
                'other-project'
            );

        $this->createTask(
            $otherProject,
            $otherOwner,
            'Other Workspace Task'
        );

        Sanctum::actingAs(
            $member
        );

        $response =
            $this->getJson(
                "/api/workspaces/{$workspace->id}/tasks"
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                2,
                'data.tasks'
            )
            ->assertJsonPath(
                'meta.total',
                2
            );

        $taskIds = collect(
            $response->json(
                'data.tasks'
            )
        )
            ->pluck('id')
            ->all();

        $this->assertContains(
            $firstTask->id,
            $taskIds
        );

        $this->assertContains(
            $secondTask->id,
            $taskIds
        );

        $projectIds = collect(
            $response->json(
                'data.tasks'
            )
        )
            ->pluck('project.id')
            ->all();

        $this->assertContains(
            $firstProject->id,
            $projectIds
        );

        $this->assertContains(
            $secondProject->id,
            $projectIds
        );
    }

    public function test_workspace_tasks_support_filters_and_assignee_me(): void
    {
        $owner =
            User::factory()->create();

        $member =
            User::factory()->create();

        $workspace =
            $this->createWorkspace(
                $owner
            );

        $this->addMember(
            $workspace,
            $member
        );

        $project =
            $this->createProject(
                $workspace,
                $owner
            );

        $assignedTask =
            $this->createTask(
                $project,
                $owner,
                'Assigned High Task',
                TaskStatus::Todo,
                TaskPriority::High
            );

        $assignedTask
            ->assignees()
            ->attach(
                $member->id,
                [
                    'assigned_by' =>
                        $owner->id,
                ]
            );

        $this->createTask(
            $project,
            $owner,
            'Unassigned High Task',
            TaskStatus::Todo,
            TaskPriority::High
        );

        $this->createTask(
            $project,
            $owner,
            'Assigned Wrong Status',
            TaskStatus::Completed,
            TaskPriority::High
        )
            ->assignees()
            ->attach(
                $member->id,
                [
                    'assigned_by' =>
                        $owner->id,
                ]
            );

        Sanctum::actingAs(
            $member
        );

        $this->getJson(
            "/api/workspaces/{$workspace->id}/tasks"
            .'?status=todo'
            .'&priority=high'
            .'&assignee=me'
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.tasks'
            )
            ->assertJsonPath(
                'data.tasks.0.id',
                $assignedTask->id
            )
            ->assertJsonPath(
                'meta.total',
                1
            );
    }

    public function test_workspace_tasks_are_paginated(): void
    {
        $owner =
            User::factory()->create();

        $workspace =
            $this->createWorkspace(
                $owner
            );

        $project =
            $this->createProject(
                $workspace,
                $owner
            );

        for ($index = 1; $index <= 5; $index++) {
            $this->createTask(
                $project,
                $owner,
                "Task {$index}"
            );
        }

        Sanctum::actingAs(
            $owner
        );

        $this->getJson(
            "/api/workspaces/{$workspace->id}/tasks"
            .'?page=2'
            .'&per_page=2'
        )
            ->assertOk()
            ->assertJsonCount(
                2,
                'data.tasks'
            )
            ->assertJsonPath(
                'meta.current_page',
                2
            )
            ->assertJsonPath(
                'meta.per_page',
                2
            )
            ->assertJsonPath(
                'meta.total',
                5
            )
            ->assertJsonPath(
                'meta.last_page',
                3
            )
            ->assertJsonPath(
                'meta.has_more_pages',
                true
            );
    }

    public function test_outsider_cannot_list_workspace_tasks(): void
    {
        $owner =
            User::factory()->create();

        $outsider =
            User::factory()->create();

        $workspace =
            $this->createWorkspace(
                $owner
            );

        Sanctum::actingAs(
            $outsider
        );

        $this->getJson(
            "/api/workspaces/{$workspace->id}/tasks"
        )->assertForbidden();
    }

    private function createWorkspace(
        User $owner,
        string $name = 'Task Workspace',
        string $slug = 'task-workspace'
    ): Workspace {
        $workspace =
            Workspace::query()->create([
                'owner_id' =>
                    $owner->id,

                'name' =>
                    $name,

                'slug' =>
                    $slug,
            ]);

        $workspace
            ->memberships()
            ->create([
                'user_id' =>
                    $owner->id,

                'role' =>
                    WorkspaceRole::Owner,

                'joined_at' =>
                    now(),
            ]);

        return $workspace;
    }

    private function addMember(
        Workspace $workspace,
        User $user
    ): void {
        $workspace
            ->memberships()
            ->create([
                'user_id' =>
                    $user->id,

                'role' =>
                    WorkspaceRole::Member,

                'joined_at' =>
                    now(),
            ]);
    }

    private function createProject(
        Workspace $workspace,
        User $creator,
        string $name = 'Task Project',
        string $slug = 'task-project'
    ): Project {
        return Project::query()->create([
            'workspace_id' =>
                $workspace->id,

            'created_by' =>
                $creator->id,

            'name' =>
                $name,

            'slug' =>
                $slug,

            'status' =>
                ProjectStatus::Active,
        ]);
    }

    private function createTask(
        Project $project,
        User $creator,
        string $title,
        TaskStatus $status = TaskStatus::Todo,
        TaskPriority $priority = TaskPriority::Medium
    ): Task {
        return Task::query()->create([
            'project_id' =>
                $project->id,

            'created_by' =>
                $creator->id,

            'title' =>
                $title,

            'status' =>
                $status,

            'priority' =>
                $priority,

            'position' =>
                1,
        ]);
    }
}
