<?php

declare(strict_types=1);

namespace Tests\Feature\Workspace;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreateWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_workspace(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/workspaces', [
            'name' => 'Konj Development',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Workspace created successfully.'
            )
            ->assertJsonPath(
                'data.workspace.name',
                'Konj Development'
            )
            ->assertJsonPath(
                'data.workspace.slug',
                'konj-development'
            )
            ->assertJsonPath(
                'data.workspace.owner_id',
                $user->id
            );

        $this->assertDatabaseHas('workspaces', [
            'owner_id' => $user->id,
            'name' => 'Konj Development',
            'slug' => 'konj-development',
        ]);

        $workspace = Workspace::query()->firstOrFail();

        $this->assertDatabaseHas('workspace_memberships', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::Owner->value,
        ]);
    }

    public function test_workspace_name_is_required(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/workspaces', [
            'name' => '   ',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
            ]);

        $this->assertDatabaseCount('workspaces', 0);
        $this->assertDatabaseCount(
            'workspace_memberships',
            0
        );
    }

    public function test_guest_cannot_create_workspace(): void
    {
        $this->postJson('/api/workspaces', [
            'name' => 'Guest Workspace',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('workspaces', 0);
    }

    public function test_inactive_user_cannot_create_workspace(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/workspaces', [
            'name' => 'Inactive Workspace',
        ])->assertForbidden();

        $this->assertDatabaseCount('workspaces', 0);
    }

    public function test_duplicate_workspace_names_receive_unique_slugs(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/workspaces', [
            'name' => 'Product Team',
        ])->assertCreated();

        $this->postJson('/api/workspaces', [
            'name' => 'Product Team',
        ])
            ->assertCreated()
            ->assertJsonPath(
                'data.workspace.slug',
                'product-team-2'
            );

        $this->assertDatabaseHas('workspaces', [
            'slug' => 'product-team',
        ]);

        $this->assertDatabaseHas('workspaces', [
            'slug' => 'product-team-2',
        ]);

        $this->assertDatabaseCount('workspaces', 2);
        $this->assertDatabaseCount(
            'workspace_memberships',
            2
        );
    }
}
