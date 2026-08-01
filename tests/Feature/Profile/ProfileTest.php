<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'email_verified_at' => now(),
        ]);

        $accessToken = $user
            ->createToken('phpunit')
            ->plainTextToken;

        $response = $this
            ->withToken($accessToken)
            ->postJson('/api/profile', [
                'name' => 'New Name',
                'email' => 'new@example.com',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Profile updated successfully.'
            )
            ->assertJsonPath('data.user.name', 'New Name')
            ->assertJsonPath('data.user.email', 'new@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);

        $user->refresh();

        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_remains_when_email_is_unchanged(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'email_verified_at' => now(),
        ]);

        $accessToken = $user
            ->createToken('phpunit')
            ->plainTextToken;

        $this
            ->withToken($accessToken)
            ->postJson('/api/profile', [
                'name' => 'Updated Name',
                'email' => 'test@example.com',
            ])
            ->assertOk();

        $user->refresh();

        $this->assertNotNull($user->email_verified_at);
    }

    public function test_user_cannot_use_another_users_email(): void
    {
        User::factory()->create([
            'email' => 'used@example.com',
        ]);

        $user = User::factory()->create([
            'email' => 'current@example.com',
        ]);

        $accessToken = $user
            ->createToken('phpunit')
            ->plainTextToken;

        $response = $this
            ->withToken($accessToken)
            ->postJson('/api/profile', [
                'name' => 'Test User',
                'email' => 'used@example.com',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_user_can_upload_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $accessToken = $user
            ->createToken('phpunit')
            ->plainTextToken;

        $avatar = UploadedFile::fake()->image(
            'avatar.jpg',
            600,
            600
        );

        $response = $this
            ->withToken($accessToken)
            ->post('/api/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $avatar,
            ], [
                'Accept' => 'application/json',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Profile updated successfully.'
            );

        $user->refresh();

        $this->assertNotNull($user->avatar_path);

        Storage::disk('public')->assertExists(
            $user->avatar_path
        );
    }

    public function test_uploading_new_avatar_deletes_old_avatar(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put(
            'avatars/old-avatar.jpg',
            'old-avatar-content'
        );

        $user = User::factory()->create([
            'avatar_path' => 'avatars/old-avatar.jpg',
        ]);

        $accessToken = $user
            ->createToken('phpunit')
            ->plainTextToken;

        $avatar = UploadedFile::fake()->image(
            'new-avatar.jpg',
            600,
            600
        );

        $this
            ->withToken($accessToken)
            ->post('/api/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $avatar,
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk();

        $user->refresh();

        Storage::disk('public')->assertMissing(
            'avatars/old-avatar.jpg'
        );

        Storage::disk('public')->assertExists(
            $user->avatar_path
        );
    }

    public function test_guest_cannot_update_profile(): void
    {
        $this->postJson('/api/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ])->assertUnauthorized();
    }

    public function test_user_can_update_password(): void
    {
        $user = User::factory()->create([
            'password' => 'OldPassword123',
        ]);

        $accessToken = $user
            ->createToken('phpunit')
            ->plainTextToken;

        $response = $this
            ->withToken($accessToken)
            ->putJson('/api/profile/password', [
                'current_password' => 'OldPassword123',
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Password updated successfully. Please log in again.'
            );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0
        );

        Auth::forgetGuards();

        $this
            ->withToken($accessToken)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'NewPassword123',
            'device_name' => 'phpunit',
        ])->assertOk();
    }

    public function test_current_password_must_be_correct(): void
    {
        $user = User::factory()->create([
            'password' => 'OldPassword123',
        ]);

        $accessToken = $user
            ->createToken('phpunit')
            ->plainTextToken;

        $response = $this
            ->withToken($accessToken)
            ->putJson('/api/profile/password', [
                'current_password' => 'WrongPassword',
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertDatabaseCount(
            'personal_access_tokens',
            1
        );
    }

    public function test_new_password_must_be_different(): void
    {
        $user = User::factory()->create([
            'password' => 'Password123',
        ]);

        $accessToken = $user
            ->createToken('phpunit')
            ->plainTextToken;

        $response = $this
            ->withToken($accessToken)
            ->putJson('/api/profile/password', [
                'current_password' => 'Password123',
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }
}
