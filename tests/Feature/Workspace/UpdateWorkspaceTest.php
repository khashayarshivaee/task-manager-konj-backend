<?php

declare(strict_types=1);

namespace Tests\Feature\Workspace;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UpdateWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_workspace(): void
    {
        $owner = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
            name: 'Old Workspace',
            slug: 'old-workspace',
        );

        Sanctum::actingAs($owner);

        $this->putJson(
            "/api/workspaces/{$workspace->id}",
            [
                'name' => '  Updated Workspace  ',
            ],
        )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Workspace updated successfully.',
            )
            ->assertJsonPath(
                'data.workspace.id',
                $workspace->id,
            )
            ->assertJsonPath(
                'data.workspace.name',
                'Updated Workspace',
            )
            ->assertJsonPath(
                'data.workspace.slug',
                'updated-workspace',
            );

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'owner_id' => $owner->id,
            'name' => 'Updated Workspace',
            'slug' => 'updated-workspace',
        ]);
    }

    public function test_admin_can_update_workspace(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
            name: 'Admin Workspace',
            slug: 'admin-workspace',
        );

        $workspace->memberships()->create([
            'user_id' => $admin->id,
            'role' => WorkspaceRole::Admin,
            'joined_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->putJson(
            "/api/workspaces/{$workspace->id}",
            [
                'name' => 'Managed Workspace',
            ],
        )
            ->assertOk()
            ->assertJsonPath(
                'data.workspace.name',
                'Managed Workspace',
            )
            ->assertJsonPath(
                'data.workspace.slug',
                'managed-workspace',
            );

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'name' => 'Managed Workspace',
            'slug' => 'managed-workspace',
        ]);
    }

    public function test_member_cannot_update_workspace(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
            name: 'Protected Workspace',
            slug: 'protected-workspace',
        );

        $workspace->memberships()->create([
            'user_id' => $member->id,
            'role' => WorkspaceRole::Member,
            'joined_at' => now(),
        ]);

        Sanctum::actingAs($member);

        $this->putJson(
            "/api/workspaces/{$workspace->id}",
            [
                'name' => 'Unauthorized Change',
            ],
        )->assertForbidden();

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'name' => 'Protected Workspace',
            'slug' => 'protected-workspace',
        ]);
    }

    public function test_outsider_cannot_update_workspace(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
            name: 'Private Workspace',
            slug: 'private-workspace',
        );

        Sanctum::actingAs($outsider);

        $this->putJson(
            "/api/workspaces/{$workspace->id}",
            [
                'name' => 'Outsider Change',
            ],
        )->assertForbidden();

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'name' => 'Private Workspace',
        ]);
    }

    public function test_workspace_name_is_required_when_updating(): void
    {
        $owner = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
            name: 'Valid Workspace',
            slug: 'valid-workspace',
        );

        Sanctum::actingAs($owner);

        $this->putJson(
            "/api/workspaces/{$workspace->id}",
            [
                'name' => '   ',
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
            ]);

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'name' => 'Valid Workspace',
            'slug' => 'valid-workspace',
        ]);
    }

    public function test_updating_name_generates_unique_slug(): void
    {
        $owner = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
            name: 'First Workspace',
            slug: 'first-workspace',
        );

        $this->createWorkspace(
            owner: $owner,
            name: 'Product Team',
            slug: 'product-team',
        );

        Sanctum::actingAs($owner);

        $this->putJson(
            "/api/workspaces/{$workspace->id}",
            [
                'name' => 'Product Team',
            ],
        )
            ->assertOk()
            ->assertJsonPath(
                'data.workspace.slug',
                'product-team-2',
            );

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'name' => 'Product Team',
            'slug' => 'product-team-2',
        ]);
    }

    public function test_guest_cannot_update_workspace(): void
    {
        $owner = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
            name: 'Guest Protected Workspace',
            slug: 'guest-protected-workspace',
        );

        $this->putJson(
            "/api/workspaces/{$workspace->id}",
            [
                'name' => 'Guest Change',
            ],
        )->assertUnauthorized();

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'name' => 'Guest Protected Workspace',
        ]);
    }

    private function createWorkspace(
        User $owner,
        string $name,
        string $slug,
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
}
