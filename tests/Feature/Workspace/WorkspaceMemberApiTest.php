<?php

declare(strict_types=1);

namespace Tests\Feature\Workspace;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkspaceMemberApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_member_can_list_members_in_role_order(): void
    {
        $owner = User::factory()->create([
            'name' => 'Workspace Owner',
        ]);

        $admin = User::factory()->create([
            'name' => 'Workspace Admin',
        ]);

        $member = User::factory()->create([
            'name' => 'Workspace Member',
        ]);

        $workspace = $this->createWorkspace(
            owner: $owner,
        );

        /*
         * Member is deliberately created before Admin
         * to confirm that the API sorts by role.
         */
        $this->addMembership(
            workspace: $workspace,
            user: $member,
            role: WorkspaceRole::Member,
        );

        $this->addMembership(
            workspace: $workspace,
            user: $admin,
            role: WorkspaceRole::Admin,
        );

        Sanctum::actingAs($member);

        $this->getJson(
            "/api/workspaces/{$workspace->id}/members"
        )
            ->assertOk()
            ->assertJsonCount(
                3,
                'data.members',
            )
            ->assertJsonPath(
                'data.members.0.role',
                WorkspaceRole::Owner->value,
            )
            ->assertJsonPath(
                'data.members.0.user.id',
                $owner->id,
            )
            ->assertJsonPath(
                'data.members.1.role',
                WorkspaceRole::Admin->value,
            )
            ->assertJsonPath(
                'data.members.1.user.id',
                $admin->id,
            )
            ->assertJsonPath(
                'data.members.2.role',
                WorkspaceRole::Member->value,
            )
            ->assertJsonPath(
                'data.members.2.user.id',
                $member->id,
            );
    }

    public function test_outsider_cannot_list_workspace_members(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
        );

        Sanctum::actingAs($outsider);

        $this->getJson(
            "/api/workspaces/{$workspace->id}/members"
        )->assertForbidden();
    }

    public function test_guest_cannot_list_workspace_members(): void
    {
        $owner = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
        );

        $this->getJson(
            "/api/workspaces/{$workspace->id}/members"
        )->assertUnauthorized();
    }

    public function test_owner_can_add_existing_active_user(): void
    {
        $owner = User::factory()->create();

        $candidate = User::factory()->create([
            'email' => 'new.member@example.com',
        ]);

        $workspace = $this->createWorkspace(
            owner: $owner,
        );

        Sanctum::actingAs($owner);

        $this->postJson(
            "/api/workspaces/{$workspace->id}/members",
            [
                'email' => '  NEW.MEMBER@EXAMPLE.COM  ',
                'role' => WorkspaceRole::Member->value,
            ],
        )
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Workspace member added successfully.',
            )
            ->assertJsonPath(
                'data.member.user_id',
                $candidate->id,
            )
            ->assertJsonPath(
                'data.member.role',
                WorkspaceRole::Member->value,
            )
            ->assertJsonPath(
                'data.member.user.email',
                'new.member@example.com',
            );

        $this->assertDatabaseHas(
            'workspace_memberships',
            [
                'workspace_id' => $workspace->id,
                'user_id' => $candidate->id,
                'role' => WorkspaceRole::Member->value,
            ],
        );
    }

    public function test_admin_can_add_workspace_member(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $candidate = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
        );

        $this->addMembership(
            workspace: $workspace,
            user: $admin,
            role: WorkspaceRole::Admin,
        );

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/workspaces/{$workspace->id}/members",
            [
                'email' => $candidate->email,
                'role' => WorkspaceRole::Admin->value,
            ],
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.member.role',
                WorkspaceRole::Admin->value,
            );

        $this->assertDatabaseHas(
            'workspace_memberships',
            [
                'workspace_id' => $workspace->id,
                'user_id' => $candidate->id,
                'role' => WorkspaceRole::Admin->value,
            ],
        );
    }

    public function test_member_cannot_add_workspace_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $candidate = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
        );

        $this->addMembership(
            workspace: $workspace,
            user: $member,
            role: WorkspaceRole::Member,
        );

        Sanctum::actingAs($member);

        $this->postJson(
            "/api/workspaces/{$workspace->id}/members",
            [
                'email' => $candidate->email,
                'role' => WorkspaceRole::Member->value,
            ],
        )->assertForbidden();

        $this->assertDatabaseMissing(
            'workspace_memberships',
            [
                'workspace_id' => $workspace->id,
                'user_id' => $candidate->id,
            ],
        );
    }

    public function test_inactive_user_cannot_be_added(): void
    {
        $owner = User::factory()->create();

        $inactiveUser = User::factory()->create([
            'is_active' => false,
        ]);

        $workspace = $this->createWorkspace(
            owner: $owner,
        );

        Sanctum::actingAs($owner);

        $this->postJson(
            "/api/workspaces/{$workspace->id}/members",
            [
                'email' => $inactiveUser->email,
                'role' => WorkspaceRole::Member->value,
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
            ]);

        $this->assertDatabaseMissing(
            'workspace_memberships',
            [
                'workspace_id' => $workspace->id,
                'user_id' => $inactiveUser->id,
            ],
        );
    }

    public function test_duplicate_workspace_membership_is_rejected(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
        );

        $this->addMembership(
            workspace: $workspace,
            user: $member,
            role: WorkspaceRole::Member,
        );

        Sanctum::actingAs($owner);

        $this->postJson(
            "/api/workspaces/{$workspace->id}/members",
            [
                'email' => $member->email,
                'role' => WorkspaceRole::Admin->value,
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
            ]);

        $this->assertDatabaseCount(
            'workspace_memberships',
            2,
        );
    }

    public function test_owner_can_update_member_role(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
        );

        $membership = $this->addMembership(
            workspace: $workspace,
            user: $member,
            role: WorkspaceRole::Member,
        );

        Sanctum::actingAs($owner);

        $this->putJson(
            "/api/workspaces/{$workspace->id}/members/{$membership->id}",
            [
                'role' => WorkspaceRole::Admin->value,
            ],
        )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Workspace member role updated successfully.',
            )
            ->assertJsonPath(
                'data.member.role',
                WorkspaceRole::Admin->value,
            );

        $this->assertDatabaseHas(
            'workspace_memberships',
            [
                'id' => $membership->id,
                'workspace_id' => $workspace->id,
                'user_id' => $member->id,
                'role' => WorkspaceRole::Admin->value,
            ],
        );
    }

    public function test_member_cannot_update_another_member_role(): void
    {
        $owner = User::factory()->create();
        $firstMember = User::factory()->create();
        $secondMember = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
        );

        $this->addMembership(
            workspace: $workspace,
            user: $firstMember,
            role: WorkspaceRole::Member,
        );

        $secondMembership = $this->addMembership(
            workspace: $workspace,
            user: $secondMember,
            role: WorkspaceRole::Member,
        );

        Sanctum::actingAs($firstMember);

        $this->putJson(
            "/api/workspaces/{$workspace->id}/members/{$secondMembership->id}",
            [
                'role' => WorkspaceRole::Admin->value,
            ],
        )->assertForbidden();

        $this->assertDatabaseHas(
            'workspace_memberships',
            [
                'id' => $secondMembership->id,
                'role' => WorkspaceRole::Member->value,
            ],
        );
    }

    public function test_workspace_owner_membership_cannot_be_changed(): void
    {
        $owner = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
        );

        $ownerMembership = $workspace
            ->memberships()
            ->where('user_id', $owner->id)
            ->firstOrFail();

        Sanctum::actingAs($owner);

        $this->putJson(
            "/api/workspaces/{$workspace->id}/members/{$ownerMembership->id}",
            [
                'role' => WorkspaceRole::Member->value,
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'membership',
            ]);

        $this->assertDatabaseHas(
            'workspace_memberships',
            [
                'id' => $ownerMembership->id,
                'user_id' => $owner->id,
                'role' => WorkspaceRole::Owner->value,
            ],
        );
    }

    public function test_membership_cannot_be_managed_through_another_workspace(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $secondMember = User::factory()->create();

        $firstWorkspace = $this->createWorkspace(
            owner: $firstOwner,
            name: 'First Workspace',
            slug: 'first-workspace',
        );

        $secondWorkspace = $this->createWorkspace(
            owner: $secondOwner,
            name: 'Second Workspace',
            slug: 'second-workspace',
        );

        $secondMembership = $this->addMembership(
            workspace: $secondWorkspace,
            user: $secondMember,
            role: WorkspaceRole::Member,
        );

        Sanctum::actingAs($firstOwner);

        $this->putJson(
            "/api/workspaces/{$firstWorkspace->id}/members/{$secondMembership->id}",
            [
                'role' => WorkspaceRole::Admin->value,
            ],
        )->assertNotFound();

        $this->assertDatabaseHas(
            'workspace_memberships',
            [
                'id' => $secondMembership->id,
                'workspace_id' => $secondWorkspace->id,
                'role' => WorkspaceRole::Member->value,
            ],
        );
    }

    public function test_admin_can_remove_workspace_member(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
        );

        $this->addMembership(
            workspace: $workspace,
            user: $admin,
            role: WorkspaceRole::Admin,
        );

        $memberMembership = $this->addMembership(
            workspace: $workspace,
            user: $member,
            role: WorkspaceRole::Member,
        );

        Sanctum::actingAs($admin);

        $this->deleteJson(
            "/api/workspaces/{$workspace->id}/members/{$memberMembership->id}"
        )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Workspace member removed successfully.',
            );

        $this->assertDatabaseMissing(
            'workspace_memberships',
            [
                'id' => $memberMembership->id,
            ],
        );

        $this->assertDatabaseHas('users', [
            'id' => $member->id,
        ]);
    }

    public function test_workspace_owner_membership_cannot_be_removed(): void
    {
        $owner = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
        );

        $ownerMembership = $workspace
            ->memberships()
            ->where('user_id', $owner->id)
            ->firstOrFail();

        Sanctum::actingAs($owner);

        $this->deleteJson(
            "/api/workspaces/{$workspace->id}/members/{$ownerMembership->id}"
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'membership',
            ]);

        $this->assertDatabaseHas(
            'workspace_memberships',
            [
                'id' => $ownerMembership->id,
                'user_id' => $owner->id,
                'role' => WorkspaceRole::Owner->value,
            ],
        );
    }

    private function createWorkspace(
        User $owner,
        string $name = 'Test Workspace',
        string $slug = 'test-workspace',
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

    private function addMembership(
        Workspace $workspace,
        User $user,
        WorkspaceRole $role,
    ): WorkspaceMembership {
        return $workspace
            ->memberships()
            ->create([
                'user_id' => $user->id,
                'role' => $role,
                'joined_at' => now(),
            ]);
    }
}
