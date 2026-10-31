<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\VpnUser;
use App\Services\XrayManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class VpnUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_create_vpn_user(): void
    {
        $this->postJson('/api/vpn/users', [
            'name' => 'Khashayar iPhone',
        ])
            ->assertUnauthorized();
    }

    public function test_other_authenticated_user_cannot_create_vpn_user(): void
    {
        $user = User::factory()->create([
            'email' => 'other@example.com',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        Sanctum::actingAs(
            $user,
            ['*'],
        );

        $this->postJson('/api/vpn/users', [
            'name' => 'Other User iPhone',
        ])
            ->assertForbidden();
    }

    public function test_allowed_user_can_create_vpn_user(): void
    {
        config([
            'xray.server' => 'edge.maranonline.com',
            'xray.port' => 443,
            'xray.server_name' => 'edge.maranonline.com',
            'xray.network' => 'ws',
            'xray.security' => 'tls',
            'xray.ws_host' => 'edge.maranonline.com',
            'xray.ws_path' => '/edge-stream',
            'xray.flow' => '',
        ]);

        $user = User::factory()->create([
            'email' => 'khashayarshivaee@gmail.com',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        Sanctum::actingAs(
            $user,
            ['*'],
        );

        $xrayManager = Mockery::mock(
            XrayManagerService::class,
        );

        $xrayManager
            ->shouldReceive('addUser')
            ->once()
            ->withArgs(
                static function (VpnUser $vpnUser): bool {
                    return
                        $vpnUser->name === 'Khashayar iPhone' &&
                        $vpnUser->is_active === true &&
                        $vpnUser->flow === '' &&
                        $vpnUser->uuid !== '' &&
                        $vpnUser->xray_email !== '';
                },
            );

        $this->app->instance(
            XrayManagerService::class,
            $xrayManager,
        );

        $response = $this->postJson(
            '/api/vpn/users',
            [
                'name' => 'Khashayar iPhone',
            ],
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.flow', '')
            ->assertJsonPath(
                'data.connection.server',
                'edge.maranonline.com',
            )
            ->assertJsonPath(
                'data.connection.port',
                443,
            )
            ->assertJsonPath(
                'data.connection.server_name',
                'edge.maranonline.com',
            )
            ->assertJsonPath(
                'data.connection.security',
                'tls',
            )
            ->assertJsonPath(
                'data.connection.network',
                'ws',
            )
            ->assertJsonPath(
                'data.connection.ws_host',
                'edge.maranonline.com',
            )
            ->assertJsonPath(
                'data.connection.ws_path',
                '/edge-stream',
            )
            ->assertJsonPath(
                'data.connection.public_key',
                '',
            )
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'uuid',
                    'xray_email',
                    'is_active',
                    'is_online',
                    'online_sessions',
                    'flow',
                    'stats' => [
                        'uplink_bytes',
                        'downlink_bytes',
                        'total_bytes',
                    ],
                    'created_at',
                    'connection' => [
                        'ws_host',
                        'ws_path',
                        'server',
                        'port',
                        'uuid',
                        'flow',
                        'security',
                        'network',
                        'server_name',
                        'fingerprint',
                        'public_key',
                        'short_id',
                        'url',
                    ],
                ],
            ]);

        $this->assertDatabaseCount(
            'vpn_users',
            1,
        );

        $vpnUser = VpnUser::query()->firstOrFail();

        $this->assertSame(
            'Khashayar iPhone',
            $vpnUser->name,
        );

        $this->assertSame(
            $user->id,
            $vpnUser->created_by,
        );

        $this->assertTrue(
            $vpnUser->is_active,
        );

        $this->assertNotEmpty(
            $vpnUser->uuid,
        );

        $this->assertNotEmpty(
            $vpnUser->xray_email,
        );
    }

    public function test_name_is_required_when_creating_vpn_user(): void
    {
        $user = User::factory()->create([
            'email' => 'khashayarshivaee@gmail.com',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        Sanctum::actingAs(
            $user,
            ['*'],
        );

        $this->postJson('/api/vpn/users', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
            ]);
    }

    public function test_allowed_user_can_list_vpn_users_with_stats(): void
    {
        config([
            'xray.server' => 'edge.maranonline.com',
            'xray.port' => 443,
            'xray.server_name' => 'edge.maranonline.com',
            'xray.network' => 'ws',
            'xray.security' => 'tls',
            'xray.ws_host' => 'edge.maranonline.com',
            'xray.ws_path' => '/edge-stream',
            'xray.flow' => '',
        ]);

        $user = User::factory()->create([
            'email' => 'khashayarshivaee@gmail.com',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        Sanctum::actingAs(
            $user,
            ['*'],
        );

        $firstVpnUser = VpnUser::query()->create([
            'name' => 'Khashayar iPhone',
            'uuid' => '11111111-1111-4111-8111-111111111111',
            'xray_email' => 'vpn-iphone@task-manager.local',
            'is_active' => true,
            'flow' => '',
            'created_by' => $user->id,
        ]);

        $secondVpnUser = VpnUser::query()->create([
            'name' => 'MacBook',
            'uuid' => '22222222-2222-4222-8222-222222222222',
            'xray_email' => 'vpn-macbook@task-manager.local',
            'is_active' => true,
            'flow' => '',
            'created_by' => $user->id,
        ]);

        $xrayManager = Mockery::mock(
            XrayManagerService::class,
        );

        $xrayManager
            ->shouldReceive('getUserStats')
            ->once()
            ->withArgs(
                static fn (VpnUser $vpnUser): bool =>
                $vpnUser->is($firstVpnUser),
            )
            ->andReturn([
                'uplink_bytes' => 1000,
                'downlink_bytes' => 9000,
                'total_bytes' => 10000,
            ]);

        $xrayManager
            ->shouldReceive('getUserStats')
            ->once()
            ->withArgs(
                static fn (VpnUser $vpnUser): bool =>
                $vpnUser->is($secondVpnUser),
            )
            ->andReturn([
                'uplink_bytes' => 2000,
                'downlink_bytes' => 18000,
                'total_bytes' => 20000,
            ]);

        $xrayManager
            ->shouldReceive('getOnlineSessionCount')
            ->once()
            ->withArgs(
                static fn (VpnUser $vpnUser): bool =>
                $vpnUser->is($firstVpnUser),
            )
            ->andReturn(0);

        $xrayManager
            ->shouldReceive('getOnlineSessionCount')
            ->once()
            ->withArgs(
                static fn (VpnUser $vpnUser): bool =>
                $vpnUser->is($secondVpnUser),
            )
            ->andReturn(1);

        $this->app->instance(
            XrayManagerService::class,
            $xrayManager,
        );

        $response = $this->getJson(
            '/api/vpn/users',
        );

        $response
            ->assertOk()
            ->assertJsonCount(
                2,
                'data',
            )
            ->assertJsonPath(
                'data.0.name',
                'MacBook',
            )
            ->assertJsonPath(
                'data.0.stats.uplink_bytes',
                2000,
            )
            ->assertJsonPath(
                'data.0.stats.downlink_bytes',
                18000,
            )
            ->assertJsonPath(
                'data.0.stats.total_bytes',
                20000,
            )
            ->assertJsonPath(
                'data.0.is_online',
                true,
            )
            ->assertJsonPath(
                'data.0.online_sessions',
                1,
            )
            ->assertJsonPath(
                'data.0.online_available',
                true,
            )
            ->assertJsonPath(
                'data.1.name',
                'Khashayar iPhone',
            )
            ->assertJsonPath(
                'data.1.stats.uplink_bytes',
                1000,
            )
            ->assertJsonPath(
                'data.1.stats.downlink_bytes',
                9000,
            )
            ->assertJsonPath(
                'data.1.stats.total_bytes',
                10000,
            )
            ->assertJsonPath(
                'data.1.is_online',
                false,
            )
            ->assertJsonPath(
                'data.1.online_sessions',
                0,
            )
            ->assertJsonPath(
                'data.1.online_available',
                true,
            )
            ->assertJsonPath(
                'data.0.connection.server',
                'edge.maranonline.com',
            )
            ->assertJsonPath(
                'data.0.connection.security',
                'tls',
            )
            ->assertJsonPath(
                'data.0.connection.network',
                'ws',
            )
            ->assertJsonPath(
                'data.0.connection.ws_host',
                'edge.maranonline.com',
            )
            ->assertJsonPath(
                'data.0.connection.ws_path',
                '/edge-stream',
            )
            ->assertJsonPath(
                'data.0.connection.port',
                443,
            );
    }

    public function test_allowed_user_can_revoke_vpn_user(): void
    {
        $user = User::factory()->create([
            'email' => 'khashayarshivaee@gmail.com',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        Sanctum::actingAs(
            $user,
            ['*'],
        );

        $vpnUser = VpnUser::query()->create([
            'name' => 'Khashayar iPhone',
            'uuid' => '11111111-1111-4111-8111-111111111111',
            'xray_email' => 'vpn-iphone@task-manager.local',
            'is_active' => true,
            'flow' => 'xtls-rprx-vision',
            'created_by' => $user->id,
        ]);

        $xrayManager = Mockery::mock(
            XrayManagerService::class,
        );

        $xrayManager
            ->shouldReceive('removeUser')
            ->once()
            ->withArgs(
                static fn (VpnUser $userToRemove): bool =>
                $userToRemove->is($vpnUser),
            );

        $this->app->instance(
            XrayManagerService::class,
            $xrayManager,
        );

        $response = $this->deleteJson(
            "/api/vpn/users/{$vpnUser->id}",
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $vpnUser->id,
            )
            ->assertJsonPath(
                'data.is_active',
                false,
            )
            ->assertJsonPath(
                'data.is_online',
                false,
            )
            ->assertJsonPath(
                'data.online_sessions',
                0,
            )
            ->assertJsonPath(
                'data.revoked_at',
                fn (mixed $value): bool =>
                    is_string($value) &&
                    $value !== '',
            );

        $vpnUser->refresh();

        $this->assertFalse(
            $vpnUser->is_active,
        );

        $this->assertNotNull(
            $vpnUser->revoked_at,
        );

        $this->assertDatabaseHas(
            'vpn_users',
            [
                'id' => $vpnUser->id,
                'is_active' => false,
            ],
        );
    }

    public function test_other_authenticated_user_cannot_revoke_vpn_user(): void
    {
        $creator = User::factory()->create();

        $vpnUser = VpnUser::query()->create([
            'name' => 'Test VPN',
            'uuid' => '22222222-2222-4222-8222-222222222222',
            'xray_email' => 'vpn-test@task-manager.local',
            'is_active' => true,
            'flow' => 'xtls-rprx-vision',
            'created_by' => $creator->id,
        ]);

        $user = User::factory()->create([
            'email' => 'other@example.com',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        Sanctum::actingAs(
            $user,
            ['*'],
        );

        $this->deleteJson(
            "/api/vpn/users/{$vpnUser->id}",
        )
            ->assertForbidden();

        $vpnUser->refresh();

        $this->assertTrue(
            $vpnUser->is_active,
        );
    }

    public function test_allowed_user_can_enable_revoked_vpn_user(): void
    {
        $user = User::factory()->create([
            'email' => 'khashayarshivaee@gmail.com',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        Sanctum::actingAs(
            $user,
            ['*'],
        );

        $vpnUser = VpnUser::query()->create([
            'name' => 'Khashayar iPhone',
            'uuid' => '33333333-3333-4333-8333-333333333333',
            'xray_email' => 'vpn-iphone@task-manager.local',
            'is_active' => false,
            'flow' => 'xtls-rprx-vision',
            'created_by' => $user->id,
            'revoked_at' => now(),
        ]);

        $xrayManager = Mockery::mock(
            XrayManagerService::class,
        );

        $xrayManager
            ->shouldReceive('addUser')
            ->once()
            ->withArgs(
                static fn (VpnUser $userToEnable): bool =>
                $userToEnable->is($vpnUser),
            );

        $this->app->instance(
            XrayManagerService::class,
            $xrayManager,
        );

        $response = $this->postJson(
            "/api/vpn/users/{$vpnUser->id}/enable",
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $vpnUser->id,
            )
            ->assertJsonPath(
                'data.is_active',
                true,
            )
            ->assertJsonPath(
                'data.is_online',
                false,
            )
            ->assertJsonPath(
                'data.online_sessions',
                0,
            )
            ->assertJsonPath(
                'data.revoked_at',
                null,
            );

        $vpnUser->refresh();

        $this->assertTrue(
            $vpnUser->is_active,
        );

        $this->assertNull(
            $vpnUser->revoked_at,
        );
    }
}
