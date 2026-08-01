<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'device_name' => 'phpunit',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.user.email', 'test@example.com')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure([
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                    ],
                    'access_token',
                    'token_type',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_registration_requires_valid_data(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => '',
            'email' => 'invalid-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'email',
                'password',
            ]);
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Another User',
            'email' => 'TEST@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_user_can_login(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'Password123',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'Password123',
            'device_name' => 'phpunit',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.user.email', 'test@example.com')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                ],
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'Password123',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'WrongPassword',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath(
                'message',
                'The provided credentials are incorrect.'
            );

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_authenticated_user_can_be_retrieved(): void
    {
        $user = User::factory()->create();

        $accessToken = $user
            ->createToken('phpunit')
            ->plainTextToken;

        $response = $this
            ->withToken($accessToken)
            ->getJson('/api/auth/me');

        $response
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.email', $user->email);
    }

    public function test_guest_cannot_access_authenticated_user(): void
    {
        $this->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_user_can_logout_current_token(): void
    {
        $user = User::factory()->create();

        $accessToken = $user
            ->createToken('phpunit')
            ->plainTextToken;

        $this
            ->withToken($accessToken)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');

        $this->assertDatabaseCount('personal_access_tokens', 0);

        Auth::forgetGuards();

        $this
            ->withToken($accessToken)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => 'Password123',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'Password123',
            'device_name' => 'phpunit',
        ]);

        $response
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Your account has been deactivated.'
            );

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_successful_login_records_time_and_ip_address(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'Password123',
        ]);

        $this
            ->withServerVariables([
                'REMOTE_ADDR' => '203.0.113.10',
            ])
            ->postJson('/api/auth/login', [
                'email' => 'test@example.com',
                'password' => 'Password123',
                'device_name' => 'phpunit',
            ])
            ->assertOk();

        $user->refresh();

        $this->assertNotNull($user->last_login_at);
        $this->assertSame('203.0.113.10', $user->last_login_ip);
    }

    public function test_inactive_user_cannot_use_existing_token(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $accessToken = $user
            ->createToken('phpunit')
            ->plainTextToken;

        $user->forceFill([
            'is_active' => false,
        ])->save();

        $response = $this
            ->withToken($accessToken)
            ->getJson('/api/auth/me');

        $response
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Your account has been deactivated.'
            );

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }
}
