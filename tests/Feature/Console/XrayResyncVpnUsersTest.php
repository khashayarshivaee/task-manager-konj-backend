<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\User;
use App\Models\VpnUser;
use App\Services\XrayManagerService;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class XrayResyncVpnUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_restores_only_missing_active_vpn_users(): void
    {
        $creator = User::factory()->create();

        $alreadyPresent = VpnUser::query()->create([
            'name' => 'Existing iPhone',
            'uuid' => '11111111-1111-4111-8111-111111111111',
            'xray_email' => 'existing@task-manager.local',
            'is_active' => true,
            'flow' => 'xtls-rprx-vision',
            'created_by' => $creator->id,
        ]);

        $missing = VpnUser::query()->create([
            'name' => 'Missing MacBook',
            'uuid' => '22222222-2222-4222-8222-222222222222',
            'xray_email' => 'missing@task-manager.local',
            'is_active' => true,
            'flow' => 'xtls-rprx-vision',
            'created_by' => $creator->id,
        ]);

        $revoked = VpnUser::query()->create([
            'name' => 'Revoked iPad',
            'uuid' => '33333333-3333-4333-8333-333333333333',
            'xray_email' => 'revoked@task-manager.local',
            'is_active' => false,
            'flow' => 'xtls-rprx-vision',
            'created_by' => $creator->id,
            'revoked_at' => now(),
        ]);

        $xrayManager = Mockery::mock(
            XrayManagerService::class,
        );

        $xrayManager
            ->shouldReceive('getInboundUserEmails')
            ->once()
            ->andReturn([
                $alreadyPresent->xray_email,
            ]);

        $xrayManager
            ->shouldReceive('addUser')
            ->once()
            ->withArgs(
                static fn (VpnUser $vpnUser): bool =>
                $vpnUser->is($missing),
            );

        $this->app->instance(
            XrayManagerService::class,
            $xrayManager,
        );

        $this->artisan('vpn:xray-resync')
            ->expectsOutput(
                'Xray VPN resync completed: 1 added, 1 already present, 0 failed.',
            )
            ->assertExitCode(
                Command::SUCCESS,
            );

        $this->assertFalse(
            $revoked->fresh()->is_active,
        );
    }

    public function test_it_fails_when_current_xray_users_cannot_be_read(): void
    {
        $xrayManager = Mockery::mock(
            XrayManagerService::class,
        );

        $xrayManager
            ->shouldReceive('getInboundUserEmails')
            ->once()
            ->andThrow(
                new \RuntimeException(
                    'Xray API unavailable.',
                ),
            );

        $xrayManager
            ->shouldNotReceive('addUser');

        $this->app->instance(
            XrayManagerService::class,
            $xrayManager,
        );

        $this->artisan('vpn:xray-resync')
            ->expectsOutput(
                'Unable to read current Xray users.',
            )
            ->assertExitCode(
                Command::FAILURE,
            );
    }
}
