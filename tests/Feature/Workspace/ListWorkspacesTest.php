<?php

declare(strict_types=1);

namespace Tests\Feature\Workspace;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListWorkspacesTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_only_accessible_workspaces_in_latest_order(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownedWorkspace = $this->createWorkspace(
            owner: $user,
            name: 'Owned Workspace',
            slug: 'owned-workspace',
        );

        $ownedWorkspace->forceFill([
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ])->saveQuietly();

        $sharedWorkspace = $this->createWorkspace(
            owner: $otherUser,
            name: 'Shared Workspace',
            slug: 'shared-workspace',
        );

        $sharedWorkspace->memberships()->create([
            'user_id' => $user->id,
            'role' => WorkspaceRole::Member,
            'joined_at' => now(),
        ]);

        $hiddenWorkspace = $this->createWorkspace(
            owner: $otherUser,
            name: 'Hidden Workspace',
            slug: 'hidden-workspace',
        );

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/workspaces');

        $response
            ->assertOk()
            ->assertJsonCount(
                2,
                'data.workspaces'
            )
            ->assertJsonPath(
                'data.workspaces.0.id',
                $sharedWorkspace->id
            )
            ->assertJsonPath(
                'data.workspaces.0.name',
                'Shared Workspace'
            )
            ->assertJsonPath(
                'data.workspaces.0.owner.id',
                $otherUser->id
            )
            ->assertJsonPath(
                'data.workspaces.0.membership.role',
                WorkspaceRole::Member->value
            )
            ->assertJsonPath(
                'data.workspaces.1.id',
                $ownedWorkspace->id
            )
            ->assertJsonPath(
                'data.workspaces.1.name',
                'Owned Workspace'
            )
            ->assertJsonPath(
                'data.workspaces.1.owner.id',
                $user->id
            )
         ->assertJsonPath(
             'data.workspaces.1.membership.role',
             WorkspaceRole::Owner->value
         );

         $workspaceIds = collect(
             $response->json('data.workspaces')
         )
             ->pluck('id')
             ->all();

         $this->assertSame([
             $sharedWorkspace->id,
             $ownedWorkspace->id,
         ], $workspaceIds);

         $this->assertNotContains(
             $hiddenWorkspace->id,
             $workspaceIds
         );
    }

    public function test_authenticated_user_with_no_workspaces_receives_empty_list(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/workspaces')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'workspaces' => [],
                ],
            ]);
    }

    public function test_guest_cannot_list_workspaces(): void
    {
        $this->getJson('/api/workspaces')
            ->assertUnauthorized();
    }

    public function test_inactive_user_cannot_list_workspaces(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/workspaces')
            ->assertForbidden();
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
