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
