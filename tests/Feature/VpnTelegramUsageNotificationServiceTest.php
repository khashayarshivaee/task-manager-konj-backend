<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendVpnTelegramUsageNotification;
use App\Models\VpnUser;
use App\Services\VpnTelegramUsageNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class VpnTelegramUsageNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private const GB = 1024 * 1024 * 1024;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
    }

    public function test_usage_below_three_gb_creates_no_notification(): void
    {
        $vpnUser = $this->createVpnUser();

        $this->connectTelegram($vpnUser);

        $this->recordUsage(
            $vpnUser,
            2 * self::GB,
        );

        $this->assertSame(0, $this->detect());

        $this->assertDatabaseCount(
            'vpn_telegram_usage_notifications',
            0,
        );

        Bus::assertNotDispatched(
            SendVpnTelegramUsageNotification::class,
        );
    }

    public function test_three_and_five_gb_trigger_separate_notifications(): void
    {
        $vpnUser = $this->createVpnUser();

        $this->connectTelegram($vpnUser);

        $this->recordUsage(
            $vpnUser,
            3 * self::GB,
        );

        $this->assertSame(1, $this->detect());

        $this->assertDatabaseHas(
            'vpn_telegram_usage_notifications',
            [
                'vpn_user_id' => $vpnUser->id,
                'threshold_gb' => 3,
            ],
        );

        Bus::assertDispatched(
            SendVpnTelegramUsageNotification::class,
            1,
        );

        /*
         * Simulate successful delivery.
         */
        DB::table(
            'vpn_telegram_usage_notifications',
        )
            ->where('vpn_user_id', $vpnUser->id)
            ->where('threshold_gb', 3)
            ->update([
                'sent_at' => now(),
            ]);

        /*
         * Repeated scheduler execution
         * must not create another alert.
         */
        $this->assertSame(0, $this->detect());

        $this->updateUsage(
            $vpnUser,
            5 * self::GB,
        );

        $this->assertSame(1, $this->detect());

        $this->assertDatabaseHas(
            'vpn_telegram_usage_notifications',
            [
                'vpn_user_id' => $vpnUser->id,
                'threshold_gb' => 5,
            ],
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

    public function test_jump_to_six_gb_triggers_both_thresholds(): void
    {
        $vpnUser = $this->createVpnUser();

        $this->connectTelegram($vpnUser);

        $this->recordUsage(
            $vpnUser,
            6 * self::GB,
        );

        $this->assertSame(2, $this->detect());

        $this->assertDatabaseCount(
            'vpn_telegram_usage_notifications',
            2,
        );

        Bus::assertDispatched(
            SendVpnTelegramUsageNotification::class,
            2,
        );
    }

    public function test_disconnected_telegram_receives_no_new_notifications(): void
    {
        $vpnUser = $this->createVpnUser();

        $this->connectTelegram(
            $vpnUser,
            false,
        );

        $this->recordUsage(
            $vpnUser,
            6 * self::GB,
        );

        $this->assertSame(0, $this->detect());

        $this->assertDatabaseCount(
            'vpn_telegram_usage_notifications',
            0,
        );
    }

    public function test_no_telegram_connection_creates_no_notification(): void
    {
        $vpnUser = $this->createVpnUser();

        $this->recordUsage(
            $vpnUser,
            6 * self::GB,
        );

        $this->assertSame(0, $this->detect());

        $this->assertDatabaseCount(
            'vpn_telegram_usage_notifications',
            0,
        );
    }

    public function test_activation_baseline_skips_old_three_gb_but_allows_five_gb(): void
    {
        $vpnUser = $this->createVpnUser();

        $this->connectTelegram($vpnUser);

        /*
         * Telegram was connected
         * when usage was already 4 GB.
         */
        $this->recordUsage(
            $vpnUser,
            4 * self::GB,
        );

        $this->createBaseline(
            $vpnUser,
            4 * self::GB,
        );

        $this->assertSame(0, $this->detect());

        /*
         * Usage later reaches 5 GB.
         */
        $this->updateUsage(
            $vpnUser,
            5 * self::GB,
        );

        $this->assertSame(1, $this->detect());

        $this->assertDatabaseMissing(
            'vpn_telegram_usage_notifications',
            [
                'vpn_user_id' => $vpnUser->id,
                'threshold_gb' => 3,
            ],
        );

        $this->assertDatabaseHas(
            'vpn_telegram_usage_notifications',
            [
                'vpn_user_id' => $vpnUser->id,
                'threshold_gb' => 5,
            ],
        );
    }

    public function test_consumption_above_five_gb_before_activation_is_ignored(): void
    {
        $vpnUser = $this->createVpnUser();

        $this->connectTelegram($vpnUser);

        $this->recordUsage(
            $vpnUser,
            6 * self::GB,
        );

        $this->createBaseline(
            $vpnUser,
            6 * self::GB,
        );

        $this->assertSame(0, $this->detect());

        /*
         * Continued usage does not
         * reactivate historical alerts.
         */
        $this->updateUsage(
            $vpnUser,
            7 * self::GB,
        );

        $this->assertSame(0, $this->detect());

        $this->assertDatabaseCount(
            'vpn_telegram_usage_notifications',
            0,
        );
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
        bool $active = true,
    ): void {
        DB::table(
            'vpn_telegram_connections',
        )->insert([
            'vpn_user_id' => $vpnUser->id,
            'telegram_chat_id' => 123456,
            'telegram_user_id' => 123456,
            'telegram_username' => 'konjtester',
            'is_active' => $active,
            'connected_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function recordUsage(
        VpnUser $vpnUser,
        int $totalBytes,
    ): void {
        DB::table(
            'vpn_user_daily_usages',
        )->insert([
            'vpn_user_id' => $vpnUser->id,
            'usage_date' => $this->today(),
            'downlink_bytes' => $totalBytes,
            'uplink_bytes' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function updateUsage(
        VpnUser $vpnUser,
        int $totalBytes,
    ): void {
        DB::table(
            'vpn_user_daily_usages',
        )
            ->where('vpn_user_id', $vpnUser->id)
            ->where('usage_date', $this->today())
            ->update([
                'downlink_bytes' => $totalBytes,
                'updated_at' => now(),
            ]);
    }

    private function createBaseline(
        VpnUser $vpnUser,
        int $initialBytes,
    ): void {
        DB::table(
            'vpn_telegram_usage_baselines',
        )->insert([
            'vpn_user_id' => $vpnUser->id,
            'usage_date' => $this->today(),
            'initial_total_bytes' => $initialBytes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function today(): string
    {
        return CarbonImmutable::today('UTC')
            ->toDateString();
    }

    private function detect(): int
    {
        return app(
            VpnTelegramUsageNotificationService::class,
        )->detect();
    }
}
