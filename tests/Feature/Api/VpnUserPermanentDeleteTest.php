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

class VpnUserPermanentDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_permanently_delete_vpn_user(): void
    {
        $admin = User::factory()->create([
            'email' => 'khashayarshivaee@gmail.com',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin, ['*']);

        $vpnUser = VpnUser::query()->create([
            'name' => 'Temporary VPN',
            'uuid' => '77777777-7777-4777-8777-777777777777',
            'xray_email' => 'permanent-test@task-manager.local',
            'is_active' => true,
            'flow' => '',
            'created_by' => $admin->id,
        ]);

        $xrayManager = Mockery::mock(
            XrayManagerService::class,
        );

        $xrayManager
            ->shouldReceive('getInboundUserEmails')
            ->once()
            ->andReturn([
                $vpnUser->xray_email,
            ]);

        $xrayManager
            ->shouldReceive('removeUser')
            ->once()
            ->withArgs(
                static fn (VpnUser $user): bool =>
                $user->is($vpnUser),
            );

        $xrayManager->shouldNotReceive('addUser');

        $this->app->instance(
            XrayManagerService::class,
            $xrayManager,
        );

        $this->deleteJson(
            "/api/vpn/users/{$vpnUser->id}/permanent",
            [
                'confirmation' => 'DELETE',
                'confirm_name' => $vpnUser->name,
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('vpn_users', [
            'id' => $vpnUser->id,
        ]);
    }

    public function test_cannot_permanently_delete_when_xray_is_unavailable(): void
    {
        $admin = User::factory()->create([
            'email' => 'khashayarshivaee@gmail.com',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin, ['*']);

        $vpnUser = VpnUser::query()->create([
            'name' => 'Protected VPN',
            'uuid' => '88888888-8888-4888-8888-888888888888',
            'xray_email' => 'protected-test@task-manager.local',
            'is_active' => true,
            'flow' => '',
            'created_by' => $admin->id,
        ]);

        $xrayManager = Mockery::mock(
            XrayManagerService::class,
        );

        $xrayManager
            ->shouldReceive('getInboundUserEmails')
            ->once()
            ->andThrow(
                new \RuntimeException('Xray API unavailable.'),
            );

        $xrayManager->shouldNotReceive('removeUser');
        $xrayManager->shouldNotReceive('addUser');

        $this->app->instance(
            XrayManagerService::class,
            $xrayManager,
        );

        $this->deleteJson(
            "/api/vpn/users/{$vpnUser->id}/permanent",
            [
                'confirmation' => 'DELETE',
                'confirm_name' => $vpnUser->name,
            ],
        )
            ->assertStatus(503);

        $this->assertDatabaseHas('vpn_users', [
            'id' => $vpnUser->id,
            'is_active' => true,
        ]);
    }

    public function test_unauthorized_user_cannot_permanently_delete_vpn_user(): void
    {
        $owner = User::factory()->create();

        $vpnUser = VpnUser::query()->create([
            'name' => 'Protected VPN',
            'uuid' => '99999999-9999-4999-8999-999999999999',
            'xray_email' => 'unauthorized-test@task-manager.local',
            'is_active' => true,
            'flow' => '',
            'created_by' => $owner->id,
        ]);

        $otherUser = User::factory()->create([
            'email' => 'other@example.com',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        Sanctum::actingAs($otherUser, ['*']);

        $xrayManager = Mockery::mock(
            XrayManagerService::class,
        );

        $xrayManager->shouldNotReceive(
            'getInboundUserEmails',
        );

        $xrayManager->shouldNotReceive('removeUser');
        $xrayManager->shouldNotReceive('addUser');

        $this->app->instance(
            XrayManagerService::class,
            $xrayManager,
        );

        $this->deleteJson(
            "/api/vpn/users/{$vpnUser->id}/permanent",
            [
                'confirmation' => 'DELETE',
                'confirm_name' => $vpnUser->name,
            ],
        )->assertForbidden();

        $this->assertDatabaseHas('vpn_users', [
            'id' => $vpnUser->id,
            'is_active' => true,
        ]);
    }
    public function test_can_permanently_delete_inactive_vpn_user(): void
    {
        $admin = User::factory()->create([
            'email' => 'khashayarshivaee@gmail.com',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin, ['*']);

        $vpnUser = VpnUser::query()->create([
            'name' => 'Inactive VPN',
            'uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'xray_email' => 'inactive-test@task-manager.local',
            'is_active' => false,
            'flow' => '',
            'created_by' => $admin->id,
            'revoked_at' => now(),
        ]);

        $xrayManager = Mockery::mock(
            XrayManagerService::class,
        );

        $xrayManager
            ->shouldReceive('getInboundUserEmails')
            ->once()
            ->andReturn([]);

        $xrayManager->shouldNotReceive('removeUser');
        $xrayManager->shouldNotReceive('addUser');

        $this->app->instance(
            XrayManagerService::class,
            $xrayManager,
        );

        $this->deleteJson(
            "/api/vpn/users/{$vpnUser->id}/permanent",
            [
                'confirmation' => 'DELETE',
                'confirm_name' => $vpnUser->name,
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('vpn_users', [
            'id' => $vpnUser->id,
        ]);
    }

    public function test_cannot_permanently_delete_when_xray_remove_fails(): void
    {
        $admin = User::factory()->create([
            'email' => 'khashayarshivaee@gmail.com',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin, ['*']);

        $vpnUser = VpnUser::query()->create([
            'name' => 'Removal Failure VPN',
            'uuid' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'xray_email' => 'remove-failure@task-manager.local',
            'is_active' => true,
            'flow' => '',
            'created_by' => $admin->id,
        ]);

        $xrayManager = Mockery::mock(
            XrayManagerService::class,
        );

        $xrayManager
            ->shouldReceive('getInboundUserEmails')
            ->once()
            ->andReturn([
                $vpnUser->xray_email,
            ]);

        $xrayManager
            ->shouldReceive('removeUser')
            ->once()
            ->withArgs(
                static fn (VpnUser $user): bool =>
                $user->is($vpnUser),
            )
            ->andThrow(
                new \RuntimeException('Xray removal failed.'),
            );

        $xrayManager->shouldNotReceive('addUser');

        $this->app->instance(
            XrayManagerService::class,
            $xrayManager,
        );

        $this->deleteJson(
            "/api/vpn/users/{$vpnUser->id}/permanent",
            [
                'confirmation' => 'DELETE',
                'confirm_name' => $vpnUser->name,
            ],
        )
            ->assertStatus(503)
            ->assertJsonPath(
                'message',
                'Unable to verify or remove VPN user from Xray.',
            );

        $this->assertDatabaseHas('vpn_users', [
            'id' => $vpnUser->id,
            'is_active' => true,
        ]);
    }
}
