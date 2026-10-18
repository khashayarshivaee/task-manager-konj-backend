<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendVpnTelegramUsageNotification;
use App\Models\User;
use App\Models\VpnUser;
use App\Services\VpnUsageCollectorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

final class VpnCollectUsageTelegramIntegrationTest
    extends TestCase
{
    use RefreshDatabase;

    private const GB = 1024 * 1024 * 1024;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
    }

    public function test_collector_creates_admin_and_telegram_alerts(): void
    {
        $admin = $this->createAdmin();

        $vpnUser = $this->createVpnUser();

        $this->connectTelegram($vpnUser);

        $this->recordUsage(
            $vpnUser,
            2 * self::GB,
            self::GB,
        );

        $this->fakeCollector([
            'baselined' => 0,
            'collected' => 1,
            'skipped' => 0,
            'failed' => 0,
        ]);

        $this->assertSame(
            0,
            Artisan::call('vpn:xray-collect-usage'),
        );

        /*
         * Existing admin alert remains active.
         */
        $this->assertDatabaseHas(
            'vpn_usage_alerts',
            [
                'vpn_user_id' => $vpnUser->id,
                'recipient_user_id' => $admin->id,
                'threshold_gb' => 3,
            ],
        );

        /*
         * Telegram notification is created.
         */
        $this->assertDatabaseHas(
            'vpn_telegram_usage_notifications',
            [
                'vpn_user_id' => $vpnUser->id,
                'telegram_chat_id' => 123456,
                'threshold_gb' => 3,
                'sent_at' => null,
            ],
        );

        Bus::assertDispatched(
            SendVpnTelegramUsageNotification::class,
            1,
        );
    }

    public function test_telegram_detection_still_runs_when_collection_partially_fails(): void
    {
        $vpnUser = $this->createVpnUser();

        $this->connectTelegram($vpnUser);

        $this->recordUsage(
            $vpnUser,
            5 * self::GB,
            0,
        );

        $this->fakeCollector([
            'baselined' => 0,
            'collected' => 0,
            'skipped' => 0,
            'failed' => 1,
        ]);

        /*
         * Collector reports a failure,
         * but still checks stored usage.
         */
        $this->assertSame(
            1,
            Artisan::call('vpn:xray-collect-usage'),
        );

        $this->assertDatabaseCount(
            'vpn_telegram_usage_notifications',
            2,
        );

        Bus::assertDispatched(
            SendVpnTelegramUsageNotification::class,
            2,
        );
    }

    public function test_admin_alerts_work_without_telegram_connection(): void
    {
        $admin = $this->createAdmin();

        $vpnUser = $this->createVpnUser();

        $this->recordUsage(
            $vpnUser,
            3 * self::GB,
            0,
        );

        $this->fakeCollector();

        $this->assertSame(
            0,
            Artisan::call('vpn:xray-collect-usage'),
        );

        /*
         * Admin notification still exists.
         */
        $this->assertDatabaseHas(
            'vpn_usage_alerts',
            [
                'vpn_user_id' => $vpnUser->id,
                'recipient_user_id' => $admin->id,
                'threshold_gb' => 3,
            ],
        );

        /*
         * No Telegram connection:
         * no Telegram notification.
         */
        $this->assertDatabaseCount(
            'vpn_telegram_usage_notifications',
            0,
        );

        Bus::assertNotDispatched(
            SendVpnTelegramUsageNotification::class,
        );
    }

    public function test_telegram_detection_failure_does_not_break_admin_alerts(): void
    {
        $admin = $this->createAdmin();

        $vpnUser = $this->createVpnUser();

        $this->recordUsage(
            $vpnUser,
            3 * self::GB,
            0,
        );

        $this->fakeCollector();

        /*
         * Simulate a database error only
         * when Telegram detection reads
         * its usage baseline.
         *
         * No database table is deleted
         * or modified for this failure.
         */
        DB::listen(
            static function ($query): void {
                if (
                    str_contains(
                        $query->sql,
                        'vpn_telegram_usage_baselines',
                    )
                ) {
                    throw new \RuntimeException(
                        'Simulated Telegram detection failure.',
                    );
                }
            },
        );

        /*
         * Telegram failure is isolated.
         * Collector command remains successful.
         */
        $this->assertSame(
            0,
            Artisan::call('vpn:xray-collect-usage'),
        );

        /*
         * Admin alert must still exist.
         */
        $this->assertDatabaseHas(
            'vpn_usage_alerts',
            [
                'vpn_user_id' => $vpnUser->id,
                'recipient_user_id' => $admin->id,
                'threshold_gb' => 3,
            ],
        );

        $this->assertStringContainsString(
            'VPN Telegram notification detection failed.',
            Artisan::output(),
        );
    }

    private function createAdmin(): User
    {
        return User::factory()->create([
            'email' => 'khashayarshivaee@gmail.com',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    private function createVpnUser(): VpnUser
    {
        return VpnUser::query()->create([
            'name' => 'ABBAS',
            'uuid' => (string) Str::uuid(),
            'xray_email' =>
                Str::uuid() . '@example.test',
            'is_active' => true,
            'flow' => 'xtls-rprx-vision',
        ]);
    }

    private function connectTelegram(
        VpnUser $vpnUser,
    ): void {
        DB::table(
            'vpn_telegram_connections',
        )->insert([
            'vpn_user_id' => $vpnUser->id,
            'telegram_chat_id' => 123456,
            'telegram_user_id' => 123456,
            'telegram_username' => 'konjtester',
            'is_active' => true,
            'connected_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function recordUsage(
        VpnUser $vpnUser,
        int $download,
        int $upload,
    ): void {
        DB::table(
            'vpn_user_daily_usages',
        )->insert([
            'vpn_user_id' => $vpnUser->id,
            'usage_date' => now('UTC')
                ->toDateString(),
            'downlink_bytes' => $download,
            'uplink_bytes' => $upload,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function fakeCollector(
        array $result = [
            'baselined' => 0,
            'collected' => 1,
            'skipped' => 0,
            'failed' => 0,
        ],
    ): void {
        $collector = Mockery::mock(
            VpnUsageCollectorService::class,
        );

        $collector->shouldReceive('collect')
            ->once()
            ->andReturn($result);

        $this->app->instance(
            VpnUsageCollectorService::class,
            $collector,
        );
    }
}
