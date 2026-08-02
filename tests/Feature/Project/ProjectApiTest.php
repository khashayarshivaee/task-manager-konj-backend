<?php

declare(strict_types=1);

namespace Tests\Feature\Project;

use App\Enums\ProjectStatus;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase;


    public function test_workspace_member_can_view_project(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $workspace = $this->createWorkspace($owner);

        $workspace->memberships()->create([
            'user_id' => $member->id,
            'role' => WorkspaceRole::Member,
            'joined_at' => now(),
        ]);

        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
            'name' => 'Visible Project',
            'slug' => 'visible-project',
            'status' => ProjectStatus::Active,
        ]);

        Sanctum::actingAs($member);

        $this->getJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}"
        )
            ->assertOk()
            ->assertJsonPath(
                'data.project.id',
                $project->id
            )
            ->assertJsonPath(
                'data.project.name',
                'Visible Project'
            );
    }

    public function test_owner_can_delete_project(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->createWorkspace($owner);

        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
            'name' => 'Project To Delete',
            'slug' => 'project-to-delete',
            'status' => ProjectStatus::Planning,
        ]);

        Sanctum::actingAs($owner);

        $this->deleteJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}"
        )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Project deleted successfully.'
            );

        $this->assertDatabaseMissing('projects', [
            'id' => $project->id,
        ]);
    }

    public function test_admin_can_delete_project(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();

        $workspace = $this->createWorkspace($owner);

        $workspace->memberships()->create([
            'user_id' => $admin->id,
            'role' => WorkspaceRole::Admin,
            'joined_at' => now(),
        ]);

        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
            'name' => 'Admin Deletable Project',
            'slug' => 'admin-deletable-project',
            'status' => ProjectStatus::Active,
        ]);

        Sanctum::actingAs($admin);

        $this->deleteJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}"
        )->assertOk();

        $this->assertDatabaseMissing('projects', [
            'id' => $project->id,
        ]);
    }

    public function test_member_cannot_delete_project(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $workspace = $this->createWorkspace($owner);

        $workspace->memberships()->create([
            'user_id' => $member->id,
            'role' => WorkspaceRole::Member,
            'joined_at' => now(),
        ]);

        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
            'name' => 'Protected Project',
            'slug' => 'protected-project',
            'status' => ProjectStatus::Planning,
        ]);

        Sanctum::actingAs($member);

        $this->deleteJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}"
        )->assertForbidden();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
        ]);
    }

    public function test_project_cannot_be_deleted_through_another_workspace(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();

        $firstWorkspace = $this->createWorkspace(
            $firstOwner
        );

        $secondWorkspace = Workspace::query()->create([
            'owner_id' => $secondOwner->id,
            'name' => 'Second Workspace',
            'slug' => 'second-workspace-delete',
        ]);

        $secondWorkspace->memberships()->create([
            'user_id' => $secondOwner->id,
            'role' => WorkspaceRole::Owner,
            'joined_at' => now(),
        ]);

        $project = Project::query()->create([
            'workspace_id' => $secondWorkspace->id,
            'created_by' => $secondOwner->id,
            'name' => 'Second Workspace Project',
            'slug' => 'second-workspace-project',
            'status' => ProjectStatus::Planning,
        ]);

        Sanctum::actingAs($firstOwner);

        $this->deleteJson(
            "/api/workspaces/{$firstWorkspace->id}/projects/{$project->id}"
        )->assertNotFound();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
        ]);
    }

    public function test_outsider_cannot_view_project(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();

        $workspace = $this->createWorkspace($owner);

        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
            'name' => 'Private Project',
            'slug' => 'private-project',
            'status' => ProjectStatus::Planning,
        ]);

        Sanctum::actingAs($outsider);

        $this->getJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}"
        )->assertForbidden();
    }

    public function test_owner_can_update_project(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->createWorkspace($owner);

        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
            'name' => 'Old Project',
            'slug' => 'old-project',
            'status' => ProjectStatus::Planning,
        ]);

        Sanctum::actingAs($owner);

        $this->putJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}",
            [
                'name' => 'Updated Project',
                'description' => 'Updated project description.',
                'status' => ProjectStatus::Active->value,
                'color' => '#ff6b00',
                'starts_at' => '2026-08-02',
                'due_at' => '2026-09-02',
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Project updated successfully.'
            )
            ->assertJsonPath(
                'data.project.name',
                'Updated Project'
            )
            ->assertJsonPath(
                'data.project.slug',
                'updated-project'
            )
            ->assertJsonPath(
                'data.project.status',
                ProjectStatus::Active->value
            );

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Updated Project',
            'slug' => 'updated-project',
            'status' => ProjectStatus::Active->value,
        ]);
    }

    public function test_member_cannot_update_project(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $workspace = $this->createWorkspace($owner);

        $workspace->memberships()->create([
            'user_id' => $member->id,
            'role' => WorkspaceRole::Member,
            'joined_at' => now(),
        ]);

        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
            'name' => 'Protected Project',
            'slug' => 'protected-project',
            'status' => ProjectStatus::Planning,
        ]);

        Sanctum::actingAs($member);

        $this->putJson(
            "/api/workspaces/{$workspace->id}/projects/{$project->id}",
            [
                'name' => 'Changed Project',
                'description' => null,
                'status' => ProjectStatus::Active->value,
                'color' => null,
                'starts_at' => null,
                'due_at' => null,
            ]
        )->assertForbidden();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Protected Project',
        ]);
    }

    public function test_project_cannot_be_accessed_through_another_workspace(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();

        $firstWorkspace = $this->createWorkspace(
            $firstOwner
        );

        $secondWorkspace = Workspace::query()->create([
            'owner_id' => $secondOwner->id,
            'name' => 'Second Workspace',
            'slug' => 'second-workspace',
        ]);

        $secondWorkspace->memberships()->create([
            'user_id' => $secondOwner->id,
            'role' => WorkspaceRole::Owner,
            'joined_at' => now(),
        ]);

        $project = Project::query()->create([
            'workspace_id' => $secondWorkspace->id,
            'created_by' => $secondOwner->id,
            'name' => 'Second Project',
            'slug' => 'second-project',
            'status' => ProjectStatus::Planning,
        ]);

        Sanctum::actingAs($firstOwner);

        $this->getJson(
            "/api/workspaces/{$firstWorkspace->id}/projects/{$project->id}"
        )->assertNotFound();
    }



    public function test_workspace_member_can_list_projects(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $workspace = $this->createWorkspace($owner);

        $workspace->memberships()->create([
            'user_id' => $member->id,
            'role' => WorkspaceRole::Member,
            'joined_at' => now(),
        ]);

        Project::query()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
            'name' => 'First Project',
            'slug' => 'first-project',
            'status' => ProjectStatus::Active,
        ]);

        Sanctum::actingAs($member);

        $this->getJson(
            "/api/workspaces/{$workspace->id}/projects"
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.projects'
            )
            ->assertJsonPath(
                'data.projects.0.name',
                'First Project'
            );
    }

    public function test_outsider_cannot_list_workspace_projects(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();

        $workspace = $this->createWorkspace($owner);

        Sanctum::actingAs($outsider);

        $this->getJson(
            "/api/workspaces/{$workspace->id}/projects"
        )->assertForbidden();
    }

    public function test_owner_can_create_project(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->createWorkspace($owner);

        Sanctum::actingAs($owner);

        $this->postJson(
            "/api/workspaces/{$workspace->id}/projects",
            [
                'name' => 'Task Manager Frontend',
                'description' => 'Ionic Angular application.',
                'status' => ProjectStatus::Active->value,
                'color' => '#ff6b00',
                'starts_at' => '2026-08-02',
                'due_at' => '2026-09-02',
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Project created successfully.'
            )
            ->assertJsonPath(
                'data.project.name',
                'Task Manager Frontend'
            )
            ->assertJsonPath(
                'data.project.slug',
                'task-manager-frontend'
            )
            ->assertJsonPath(
                'data.project.status',
                ProjectStatus::Active->value
            );

        $this->assertDatabaseHas('projects', [
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
            'slug' => 'task-manager-frontend',
        ]);
    }

    public function test_admin_can_create_project(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();

        $workspace = $this->createWorkspace($owner);

        $workspace->memberships()->create([
            'user_id' => $admin->id,
            'role' => WorkspaceRole::Admin,
            'joined_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/workspaces/{$workspace->id}/projects",
            [
                'name' => 'Admin Project',
            ]
        )->assertCreated();
    }

    public function test_member_cannot_create_project(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $workspace = $this->createWorkspace($owner);

        $workspace->memberships()->create([
            'user_id' => $member->id,
            'role' => WorkspaceRole::Member,
            'joined_at' => now(),
        ]);

        Sanctum::actingAs($member);

        $this->postJson(
            "/api/workspaces/{$workspace->id}/projects",
            [
                'name' => 'Forbidden Project',
            ]
        )->assertForbidden();

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_duplicate_names_receive_unique_slugs_within_workspace(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->createWorkspace($owner);

        Sanctum::actingAs($owner);

        $this->postJson(
            "/api/workspaces/{$workspace->id}/projects",
            [
                'name' => 'Mobile App',
            ]
        )->assertCreated();

        $this->postJson(
            "/api/workspaces/{$workspace->id}/projects",
            [
                'name' => 'Mobile App',
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.project.slug',
                'mobile-app-2'
            );
    }

    private function createWorkspace(
        User $owner
    ): Workspace {
        $workspace = Workspace::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Test Workspace',
            'slug' => 'test-workspace',
        ]);

        $workspace->memberships()->create([
            'user_id' => $owner->id,
            'role' => WorkspaceRole::Owner,
            'joined_at' => now(),
        ]);

        return $workspace;
    }
}
