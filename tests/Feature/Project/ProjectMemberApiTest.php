<?php

declare(strict_types=1);

namespace Tests\Feature\Project;

use App\Enums\ProjectStatus;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;

class ProjectMemberApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_member_can_list_project_members(): void
    {
        $owner = User::factory()->create([
            'name' => 'Workspace Owner',
        ]);

        $projectMember = User::factory()->create([
            'name' => 'Project Member',
        ]);

        $viewer = User::factory()->create([
            'name' => 'Workspace Viewer',
        ]);

        $workspace = $this->createWorkspace(
            $owner
        );

        $this->addWorkspaceMembership(
            $workspace,
            $projectMember,
            WorkspaceRole::Member
        );

        $this->addWorkspaceMembership(
            $workspace,
            $viewer,
            WorkspaceRole::Member
        );

        $project = $this->createProject(
            $workspace,
            $owner
        );

        $project
            ->memberships()
            ->create([
                'user_id' => $projectMember->id,
                'added_by' => $owner->id,
                'joined_at' => now()->addMinute(),
            ]);

        Sanctum::actingAs($viewer);

        $this->getJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}/members"
        )
            ->assertOk()
            ->assertJsonCount(
                2,
                'data.members'
            )
            ->assertJsonPath(
                'data.members.0.user.id',
                $owner->id
            )
            ->assertJsonPath(
                'data.members.1.user.id',
                $projectMember->id
            );
    }

    public function test_owner_can_add_workspace_member_to_project(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $workspace = $this->createWorkspace(
            $owner
        );

        $this->addWorkspaceMembership(
            $workspace,
            $member,
            WorkspaceRole::Member
        );

        $project = $this->createProject(
            $workspace,
            $owner
        );

        Sanctum::actingAs($owner);

        $this->postJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}/members",
            [
                'user_id' => $member->id,
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Project member added successfully.'
            )
            ->assertJsonPath(
                'data.member.user_id',
                $member->id
            )
            ->assertJsonPath(
                'data.member.added_by',
                $owner->id
            )
            ->assertJsonPath(
                'data.member.added_by_user.id',
                $owner->id
            );

        $this->assertDatabaseHas(
            'project_memberships',
            [
                'project_id' => $project->id,
                'user_id' => $member->id,
                'added_by' => $owner->id,
            ]
        );
    }

    public function test_admin_can_add_workspace_member_to_project(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();

        $workspace = $this->createWorkspace(
            $owner
        );

        $this->addWorkspaceMembership(
            $workspace,
            $admin,
            WorkspaceRole::Admin
        );

        $this->addWorkspaceMembership(
            $workspace,
            $member,
            WorkspaceRole::Member
        );

        $project = $this->createProject(
            $workspace,
            $owner
        );

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}/members",
            [
                'user_id' => $member->id,
            ]
        )->assertCreated();

        $this->assertDatabaseHas(
            'project_memberships',
            [
                'project_id' => $project->id,
                'user_id' => $member->id,
                'added_by' => $admin->id,
            ]
        );
    }

    public function test_regular_member_cannot_add_project_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $candidate = User::factory()->create();

        $workspace = $this->createWorkspace(
            $owner
        );

        $this->addWorkspaceMembership(
            $workspace,
            $member,
            WorkspaceRole::Member
        );

        $this->addWorkspaceMembership(
            $workspace,
            $candidate,
            WorkspaceRole::Member
        );

        $project = $this->createProject(
            $workspace,
            $owner
        );

        Sanctum::actingAs($member);

        $this->postJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}/members",
            [
                'user_id' => $candidate->id,
            ]
        )->assertForbidden();

        $this->assertDatabaseMissing(
            'project_memberships',
            [
                'project_id' => $project->id,
                'user_id' => $candidate->id,
            ]
        );
    }

    public function test_user_from_another_workspace_cannot_be_added(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();

        $workspace = $this->createWorkspace(
            $owner,
            'First Workspace',
            'first-workspace'
        );

        $otherWorkspace = $this->createWorkspace(
            $otherOwner,
            'Second Workspace',
            'second-workspace'
        );

        $project = $this->createProject(
            $workspace,
            $owner
        );

        Sanctum::actingAs($owner);

        $this->postJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}/members",
            [
                'user_id' => $otherOwner->id,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'user_id',
            ]);

        $this->assertDatabaseMissing(
            'project_memberships',
            [
                'project_id' => $project->id,
                'user_id' => $otherOwner->id,
            ]
        );
    }

    public function test_duplicate_project_membership_is_rejected(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $workspace = $this->createWorkspace(
            $owner
        );

        $this->addWorkspaceMembership(
            $workspace,
            $member,
            WorkspaceRole::Member
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

        Sanctum::actingAs($owner);

        $this->postJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}/members",
            [
                'user_id' => $member->id,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'user_id',
            ]);

        $this->assertSame(
            1,
            ProjectMembership::query()
                ->where(
                    'project_id',
                    $project->id
                )
                ->where(
                    'user_id',
                    $member->id
                )
                ->count()
        );
    }

    public function test_admin_can_remove_project_member(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();

        $workspace = $this->createWorkspace(
            $owner
        );

        $this->addWorkspaceMembership(
            $workspace,
            $admin,
            WorkspaceRole::Admin
        );

        $this->addWorkspaceMembership(
            $workspace,
            $member,
            WorkspaceRole::Member
        );

        $project = $this->createProject(
            $workspace,
            $owner
        );

        $membership = $project
            ->memberships()
            ->create([
                'user_id' => $member->id,
                'added_by' => $owner->id,
                'joined_at' => now(),
            ]);

        Sanctum::actingAs($admin);

        $this->deleteJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}/members/{$membership->id}"
        )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Project member removed successfully.'
            );

        $this->assertDatabaseMissing(
            'project_memberships',
            [
                'id' => $membership->id,
            ]
        );

        $this->assertDatabaseHas(
            'users',
            [
                'id' => $member->id,
            ]
        );
    }
    public function test_project_member_cannot_be_removed_while_assigned_to_task(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $workspace = $this->createWorkspace(
            $owner
        );

        $this->addWorkspaceMembership(
            $workspace,
            $member,
            WorkspaceRole::Member
        );

        $project = $this->createProject(
            $workspace,
            $owner
        );

        $membership = $project
            ->memberships()
            ->create([
                'user_id' => $member->id,
                'added_by' => $owner->id,
                'joined_at' => now(),
            ]);

   $task = Task::query()->create([
            'project_id' => $project->id,
            'created_by' => $owner->id,
            'assigned_to' => $member->id,
            'title' => 'Assigned Task',
           'status' => TaskStatus::Todo,
           'priority' => TaskPriority::Medium,
            'position' => 1,
        ]);

        $task
            ->assignees()
            ->attach(
                $member->id,
                [
                    'assigned_by' => $owner->id,
                ]
            );

        Sanctum::actingAs($owner);

        $this->deleteJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}/members/{$membership->id}"
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'membership',
            ]);

        $this->assertDatabaseHas(
            'project_memberships',
            [
                'id' => $membership->id,
                'project_id' => $project->id,
                'user_id' => $member->id,
            ]
        );
    }

    public function test_project_creator_cannot_be_removed(): void
    {
        $owner = User::factory()->create();

        $workspace = $this->createWorkspace(
            $owner
        );

        $project = $this->createProject(
            $workspace,
            $owner
        );

        $creatorMembership = $project
            ->memberships()
            ->where(
                'user_id',
                $owner->id
            )
            ->firstOrFail();

        Sanctum::actingAs($owner);

        $this->deleteJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}/members/{$creatorMembership->id}"
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'membership',
            ]);

        $this->assertDatabaseHas(
            'project_memberships',
            [
                'id' => $creatorMembership->id,
                'user_id' => $owner->id,
            ]
        );
    }

    public function test_membership_cannot_be_removed_through_another_project(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $workspace = $this->createWorkspace(
            $owner
        );

        $this->addWorkspaceMembership(
            $workspace,
            $member,
            WorkspaceRole::Member
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

        $membership = $secondProject
            ->memberships()
            ->create([
                'user_id' => $member->id,
                'added_by' => $owner->id,
                'joined_at' => now(),
            ]);

        Sanctum::actingAs($owner);

        $this->deleteJson(
            "/api/workspaces/{$workspace->id}/projects/{$firstProject->id}/members/{$membership->id}"
        )->assertNotFound();

        $this->assertDatabaseHas(
            'project_memberships',
            [
                'id' => $membership->id,
                'project_id' => $secondProject->id,
            ]
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

        $workspace
            ->memberships()
            ->create([
                'user_id' => $owner->id,
                'role' => WorkspaceRole::Owner,
                'joined_at' => now(),
            ]);

        return $workspace;
    }

    private function addWorkspaceMembership(
        Workspace $workspace,
        User $user,
        WorkspaceRole $role
    ): WorkspaceMembership {
        return $workspace
            ->memberships()
            ->create([
                'user_id' => $user->id,
                'role' => $role,
                'joined_at' => now(),
            ]);
    }

    private function createProject(
        Workspace $workspace,
        User $creator,
        string $name = 'Test Project',
        string $slug = 'test-project'
    ): Project {
        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $creator->id,
            'name' => $name,
            'slug' => $slug,
            'status' => ProjectStatus::Active,
        ]);

        $project
            ->memberships()
            ->create([
                'user_id' => $creator->id,
                'added_by' => $creator->id,
                'joined_at' => now(),
            ]);

        return $project;
    }
}
