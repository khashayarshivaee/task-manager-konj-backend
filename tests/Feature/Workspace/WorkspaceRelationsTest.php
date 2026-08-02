<?php

declare(strict_types=1);

namespace Tests\Feature\Workspace;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_own_and_belong_to_a_workspace(): void
    {
        $user = User::factory()->create();

        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Test Workspace',
            'slug' => 'test-workspace',
        ]);

        $workspace->memberships()->create([
            'user_id' => $user->id,
            'role' => WorkspaceRole::Owner,
            'joined_at' => now(),
        ]);

        $workspace->load([
            'owner',
            'users',
            'memberships',
        ]);

        $this->assertTrue(
            $workspace->owner->is($user)
        );

        $this->assertCount(
            1,
            $workspace->users
        );

        $this->assertCount(
            1,
            $workspace->memberships
        );

        $this->assertSame(
            WorkspaceRole::Owner,
            $workspace->memberships->first()->role
        );

        $this->assertSame(
            WorkspaceRole::Owner,
            $workspace->users
                ->first()
                ->membership
                ->role
        );

        $this->assertSame(
            1,
            $user->workspaces()->count()
        );
    }
}
