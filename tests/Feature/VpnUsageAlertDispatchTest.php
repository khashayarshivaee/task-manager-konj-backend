<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendVpnUsageAlertPush;
use App\Models\PushDevice;
use App\Models\User;
use App\Models\VpnUser;
use App\Services\VpnUsageAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class VpnUsageAlertDispatchTest extends TestCase
{
    use RefreshDatabase;

    private const GB = 1024 * 1024 * 1024;

    public function test_two_thresholds_queue_two_push_jobs(): void
    {
        Bus::fake();

        $admin = $this->createAdmin();

        $this->registerIphone($admin);

        $vpnUser = $this->createVpnUser();

        $this->recordUsage(
            $vpnUser,
            4 * self::GB,
            self::GB,
        );

        $created = app(
            VpnUsageAlertService::class,
        )->detect();

        $this->assertSame(2, $created);

        $this->assertDatabaseCount(
            'vpn_usage_alerts',
            2,
        );

        $alerts = DB::table('vpn_usage_alerts')
            ->orderBy('threshold_gb')
            ->get(['id', 'threshold_gb']);

        $this->assertSame(
            3,
            (int) $alerts[0]->threshold_gb,
        );

        $this->assertSame(
            5,
            (int) $alerts[1]->threshold_gb,
        );

        Bus::assertDispatched(
            SendVpnUsageAlertPush::class,
            2,
        );

        Bus::assertDispatched(
            SendVpnUsageAlertPush::class,
            fn (SendVpnUsageAlertPush $job): bool =>
                $job->alertId ===
                (int) $alerts[0]->id,
        );

        Bus::assertDispatched(
            SendVpnUsageAlertPush::class,
            fn (SendVpnUsageAlertPush $job): bool =>
                $job->alertId ===
                (int) $alerts[1]->id,
        );
    }

    public function test_pending_alert_is_queued_after_iphone_registration(): void
    {
        Bus::fake();

        $admin = $this->createAdmin();

        $vpnUser = $this->createVpnUser();

        $this->recordUsage(
            $vpnUser,
            3 * self::GB,
            0,
        );

        // No registered iPhone yet.
        $this->assertSame(
            1,
            app(VpnUsageAlertService::class)
                ->detect(),
        );

        Bus::assertNotDispatched(
            SendVpnUsageAlertPush::class,
        );

        $this->assertDatabaseCount(
            'vpn_usage_alerts',
            1,
        );

        // Register the administrator's iPhone.
        $this->registerIphone($admin);

        // No new alert, but the pending one
        // must now be queued.
        $this->assertSame(
            0,
            app(VpnUsageAlertService::class)
                ->detect(),
        );

        Bus::assertDispatched(
            SendVpnUsageAlertPush::class,
            1,
        );

        $this->assertDatabaseCount(
            'vpn_usage_alerts',
            1,
        );
    }

    private function createAdmin(): User
    {
        return User::factory()->create([
            'email' =>
                'khashayarshivaee@gmail.com',
            'is_active' => true,
        ]);
    }

    private function createVpnUser(): VpnUser
    {
        return VpnUser::query()->create([
            'name' => 'Test VPN',
            'uuid' => (string) Str::uuid(),
            'xray_email' =>
                Str::uuid() . '@example.test',
            'is_active' => true,
            'flow' => 'xtls-rprx-vision',
        ]);
    }

    private function registerIphone(User $user): void
    {
        PushDevice::query()->create([
            'user_id' => $user->id,
            'token' => 'test-apns-device-token',
            'platform' => 'ios',
            'environment' => 'development',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);
    }

    private function recordUsage(
        VpnUser $vpnUser,
        int $download,
        int $upload,
    ): void {
        DB::table('vpn_user_daily_usages')
            ->insert([
                'vpn_user_id' => $vpnUser->id,
                'usage_date' => now('UTC')
                    ->toDateString(),
                'downlink_bytes' => $download,
                'uplink_bytes' => $upload,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
