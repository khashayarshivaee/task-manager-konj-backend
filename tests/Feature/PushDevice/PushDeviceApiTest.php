<?php

declare(strict_types=1);

namespace Tests\Feature\PushDevice;

use App\Models\PushDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PushDeviceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_can_register_ios_development_device(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson(
            '/api/push-devices',
            [
                'token' => 'development-token-123',
                'platform' => 'ios',
                'environment' => 'development',
                'device_name' => 'Khashayar iPhone',
            ],
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Push device registered successfully.',
            )
            ->assertJsonPath(
                'data.device.platform',
                'ios',
            )
            ->assertJsonPath(
                'data.device.environment',
                'development',
            )
            ->assertJsonPath(
                'data.device.device_name',
                'Khashayar iPhone',
            )
            ->assertJsonPath(
                'data.device.is_active',
                true,
            );

        $response->assertJsonMissing([
            'token' => 'development-token-123',
        ]);

        $this->assertDatabaseHas(
            'push_devices',
            [
                'user_id' => $user->id,
                'token' => 'development-token-123',
                'platform' => 'ios',
                'environment' => 'development',
                'is_active' => true,
            ],
        );

        $device = PushDevice::query()
            ->firstOrFail();

        $this->assertNotNull(
            $device->last_seen_at,
        );
    }

    public function test_registering_same_device_again_does_not_create_duplicate(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $payload = [
            'token' => 'same-token',
            'platform' => 'ios',
            'environment' => 'development',
            'device_name' => 'iPhone',
        ];

        $this->postJson(
            '/api/push-devices',
            $payload,
        )->assertOk();

        $this->postJson(
            '/api/push-devices',
            $payload,
        )->assertOk();

        $this->assertDatabaseCount(
            'push_devices',
            1,
        );
    }

    public function test_same_token_can_exist_in_development_and_production(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->postJson(
            '/api/push-devices',
            [
                'token' => 'shared-token',
                'platform' => 'ios',
                'environment' => 'development',
            ],
        )->assertOk();

        $this->postJson(
            '/api/push-devices',
            [
                'token' => 'shared-token',
                'platform' => 'ios',
                'environment' => 'production',
            ],
        )->assertOk();

        $this->assertDatabaseCount(
            'push_devices',
            2,
        );

        $this->assertDatabaseHas(
            'push_devices',
            [
                'token' => 'shared-token',
                'environment' => 'development',
            ],
        );

        $this->assertDatabaseHas(
            'push_devices',
            [
                'token' => 'shared-token',
                'environment' => 'production',
            ],
        );
    }

    public function test_device_token_moves_to_current_signed_in_user(): void
    {
        $firstUser = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $secondUser = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($firstUser);

        $this->postJson(
            '/api/push-devices',
            [
                'token' => 'moved-token',
                'platform' => 'ios',
                'environment' => 'development',
            ],
        )->assertOk();

        Sanctum::actingAs($secondUser);

        $this->postJson(
            '/api/push-devices',
            [
                'token' => 'moved-token',
                'platform' => 'ios',
                'environment' => 'development',
            ],
        )->assertOk();

        $this->assertDatabaseCount(
            'push_devices',
            1,
        );

        $this->assertDatabaseHas(
            'push_devices',
            [
                'user_id' => $secondUser->id,
                'token' => 'moved-token',
                'environment' => 'development',
                'is_active' => true,
            ],
        );
    }

    public function test_user_can_unregister_current_push_device(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->postJson(
            '/api/push-devices',
            [
                'token' => 'logout-token',
                'platform' => 'ios',
                'environment' => 'development',
            ],
        )->assertOk();

        $this->deleteJson(
            '/api/push-devices',
            [
                'token' => 'logout-token',
                'environment' => 'development',
            ],
        )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Push device unregistered successfully.',
            );

        $this->assertDatabaseHas(
            'push_devices',
            [
                'user_id' => $user->id,
                'token' => 'logout-token',
                'environment' => 'development',
                'is_active' => false,
            ],
        );
    }

    public function test_registering_inactive_device_again_reactivates_it(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        PushDevice::query()->create([
            'user_id' => $user->id,
            'token' => 'reactivate-token',
            'platform' => 'ios',
            'environment' => 'development',
            'is_active' => false,
        ]);

        Sanctum::actingAs($user);

        $this->postJson(
            '/api/push-devices',
            [
                'token' => 'reactivate-token',
                'platform' => 'ios',
                'environment' => 'development',
            ],
        )->assertOk();

        $this->assertDatabaseHas(
            'push_devices',
            [
                'user_id' => $user->id,
                'token' => 'reactivate-token',
                'is_active' => true,
            ],
        );
    }

    public function test_guest_cannot_register_push_device(): void
    {
        $this->postJson(
            '/api/push-devices',
            [
                'token' => 'guest-token',
                'platform' => 'ios',
                'environment' => 'development',
            ],
        )->assertUnauthorized();

        $this->assertDatabaseCount(
            'push_devices',
            0,
        );
    }

    public function test_unverified_user_cannot_register_push_device(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        Sanctum::actingAs($user);

        $this->postJson(
            '/api/push-devices',
            [
                'token' => 'unverified-token',
                'platform' => 'ios',
                'environment' => 'development',
            ],
        )->assertForbidden();

        $this->assertDatabaseCount(
            'push_devices',
            0,
        );
    }

    public function test_inactive_user_cannot_register_push_device(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->postJson(
            '/api/push-devices',
            [
                'token' => 'inactive-token',
                'platform' => 'ios',
                'environment' => 'development',
            ],
        )->assertForbidden();

        $this->assertDatabaseCount(
            'push_devices',
            0,
        );
    }

    public function test_push_device_payload_is_validated(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->postJson(
            '/api/push-devices',
            [
                'token' => '',
                'platform' => 'android',
                'environment' => 'invalid',
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'token',
                'platform',
                'environment',
            ]);

        $this->assertDatabaseCount(
            'push_devices',
            0,
        );
    }
}
