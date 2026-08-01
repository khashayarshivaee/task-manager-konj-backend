<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_user_cannot_access_admin_users(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::User,
        ]);

        $accessToken = $user
            ->createToken('phpunit')
            ->plainTextToken;

        $this
            ->withToken($accessToken)
            ->getJson('/api/admin/users')
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'You are not authorized to access this resource.'
            );
    }

    public function test_admin_can_list_users_with_pagination(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        User::factory()->count(2)->create();

        $accessToken = $admin
            ->createToken('phpunit')
            ->plainTextToken;

        $response = $this
            ->withToken($accessToken)
            ->getJson('/api/admin/users?per_page=2');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data.users')
            ->assertJsonPath('data.pagination.per_page', 2)
            ->assertJsonPath('data.pagination.total', 3)
            ->assertJsonStructure([
                'data' => [
                    'users' => [
                        '*' => [
                            'id',
                            'name',
                            'email',
                            'role',
                            'is_active',
                        ],
                    ],
                    'pagination' => [
                        'current_page',
                        'last_page',
                        'per_page',
                        'total',
                        'from',
                        'to',
                    ],
                ],
            ]);
    }

    public function test_admin_can_search_filter_and_sort_users(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        User::factory()->create([
            'name' => 'Ali Beta',
            'role' => UserRole::User,
            'is_active' => true,
        ]);

        User::factory()->create([
            'name' => 'Ali Alpha',
            'role' => UserRole::User,
            'is_active' => true,
        ]);

        User::factory()->create([
            'name' => 'Ali Disabled',
            'role' => UserRole::User,
            'is_active' => false,
        ]);

        User::factory()->create([
            'name' => 'Other User',
            'role' => UserRole::User,
            'is_active' => true,
        ]);

        $accessToken = $admin
            ->createToken('phpunit')
            ->plainTextToken;

        $response = $this
            ->withToken($accessToken)
            ->getJson(
                '/api/admin/users'
                .'?search=Ali'
                .'&role=user'
                .'&is_active=1'
                .'&sort=name_asc'
                .'&per_page=10'
            );

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data.users')
            ->assertJsonPath(
                'data.users.0.name',
                'Ali Alpha'
            )
            ->assertJsonPath(
                'data.users.1.name',
                'Ali Beta'
            );
    }

    public function test_admin_can_view_a_user(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $user = User::factory()->create([
            'name' => 'Target User',
            'email' => 'target@example.com',
        ]);

        $accessToken = $admin
            ->createToken('phpunit')
            ->plainTextToken;

        $this
            ->withToken($accessToken)
            ->getJson("/api/admin/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath(
                'data.user.email',
                'target@example.com'
            );
    }

    public function test_admin_can_update_a_user(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'role' => UserRole::User,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $accessToken = $admin
            ->createToken('phpunit')
            ->plainTextToken;

        $response = $this
            ->withToken($accessToken)
            ->putJson("/api/admin/users/{$user->id}", [
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
                'role' => UserRole::Admin->value,
                'is_active' => true,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'User updated successfully.'
            )
            ->assertJsonPath(
                'data.user.name',
                'Updated Name'
            )
            ->assertJsonPath(
                'data.user.email',
                'updated@example.com'
            )
            ->assertJsonPath(
                'data.user.role',
                UserRole::Admin->value
            )
            ->assertJsonPath(
                'data.user.is_active',
                true
            );

        $user->refresh();

        $this->assertSame(
            UserRole::Admin,
            $user->role
        );

        $this->assertNull(
            $user->email_verified_at
        );
    }

    public function test_admin_cannot_update_own_account_from_admin_route(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $accessToken = $admin
            ->createToken('phpunit')
            ->plainTextToken;

        $this
            ->withToken($accessToken)
            ->putJson("/api/admin/users/{$admin->id}", [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => UserRole::Admin->value,
                'is_active' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Manage your own account through the profile section.'
            );
    }

    public function test_admin_cannot_assign_duplicate_email_to_user(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $user = User::factory()->create([
            'email' => 'target@example.com',
        ]);

        $accessToken = $admin
            ->createToken('phpunit')
            ->plainTextToken;

        $response = $this
            ->withToken($accessToken)
            ->putJson("/api/admin/users/{$user->id}", [
                'name' => $user->name,
                'email' => $existingUser->email,
                'role' => UserRole::User->value,
                'is_active' => true,
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_deactivating_user_revokes_all_tokens(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $user = User::factory()->create([
            'role' => UserRole::User,
            'is_active' => true,
        ]);

        $user->createToken('device-one');
        $user->createToken('device-two');

        $accessToken = $admin
            ->createToken('phpunit')
            ->plainTextToken;

        $this
            ->withToken($accessToken)
            ->putJson("/api/admin/users/{$user->id}", [
                'name' => $user->name,
                'email' => $user->email,
                'role' => UserRole::User->value,
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath(
                'data.user.is_active',
                false
            );

        $user->refresh();

        $this->assertFalse(
            $user->isActive()
        );

        $this->assertSame(
            0,
            $user->tokens()->count()
        );
    }

    public function test_admin_can_delete_user_with_avatar_and_tokens(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put(
            'avatars/target-avatar.jpg',
            'avatar-content'
        );

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $user = User::factory()->create([
            'avatar_path' => 'avatars/target-avatar.jpg',
        ]);

        $user->createToken('device-one');
        $user->createToken('device-two');

        $accessToken = $admin
            ->createToken('phpunit')
            ->plainTextToken;

        $this
            ->withToken($accessToken)
            ->deleteJson("/api/admin/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath(
                'message',
                'User deleted successfully.'
            );

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);

        $this->assertDatabaseMissing(
            'personal_access_tokens',
            [
                'tokenable_type' => User::class,
                'tokenable_id' => $user->id,
            ]
        );

        Storage::disk('public')->assertMissing(
            'avatars/target-avatar.jpg'
        );
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $accessToken = $admin
            ->createToken('phpunit')
            ->plainTextToken;

        $this
            ->withToken($accessToken)
            ->deleteJson("/api/admin/users/{$admin->id}")
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'You cannot delete your own account.'
            );

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
        ]);
    }

    public function test_inactive_admin_cannot_access_admin_users(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $accessToken = $admin
            ->createToken('phpunit')
            ->plainTextToken;

        $admin->forceFill([
            'is_active' => false,
        ])->save();

        $this
            ->withToken($accessToken)
            ->getJson('/api/admin/users')
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Your account has been deactivated.'
            );
    }
}
