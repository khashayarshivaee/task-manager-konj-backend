<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\VpnUser;
use App\Services\VpnUsageAlertService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class VpnUsageAlertBaselineTest extends TestCase
{
    use RefreshDatabase;

    private const GB = 1024 * 1024 * 1024;

    public function test_old_three_gb_is_ignored_but_new_five_gb_triggers(): void
    {
        $this->createAdmin();

        $vpnUser = $this->createVpnUser();

        /*
         * At activation, the user has
         * already consumed 4 GB.
         */
        $this->recordUsage(
            $vpnUser,
            4 * self::GB,
        );

        $this->createBaseline(
            $vpnUser,
            4 * self::GB,
        );

        $this->assertSame(
            0,
            $this->detect(),
        );

        /*
         * Later, usage reaches 5 GB.
         */
        $this->updateUsage(
            $vpnUser,
            5 * self::GB,
        );

        $this->assertSame(
            1,
            $this->detect(),
        );

        $this->assertDatabaseMissing(
            'vpn_usage_alerts',
            [
                'vpn_user_id' => $vpnUser->id,
                'threshold_gb' => 3,
            ],
        );

        $this->assertDatabaseHas(
            'vpn_usage_alerts',
            [
                'vpn_user_id' => $vpnUser->id,
                'threshold_gb' => 5,
            ],
        );

        $this->assertSame(
            0,
            $this->detect(),
        );
    }

    public function test_thresholds_reached_before_activation_are_ignored(): void
    {
        $this->createAdmin();

        $vpnUser = $this->createVpnUser();

        /*
         * Both thresholds were passed
         * before activation.
         */
        $this->recordUsage(
            $vpnUser,
            6 * self::GB,
        );

        $this->createBaseline(
            $vpnUser,
            6 * self::GB,
        );

        $this->assertSame(
            0,
            $this->detect(),
        );

        /*
         * Continued consumption must
         * not trigger old thresholds.
         */
        $this->updateUsage(
            $vpnUser,
            7 * self::GB,
        );

        $this->assertSame(
            0,
            $this->detect(),
        );

        $this->assertDatabaseCount(
            'vpn_usage_alerts',
            0,
        );
    }

    public function test_new_day_without_baseline_uses_normal_thresholds(): void
    {
        $this->createAdmin();

        $vpnUser = $this->createVpnUser();

        /*
         * No historical baseline.
         * Both thresholds are new.
         */
        $this->recordUsage(
            $vpnUser,
            5 * self::GB,
        );

        $this->assertSame(
            2,
            $this->detect(),
        );

        $this->assertDatabaseCount(
            'vpn_usage_alerts',
            2,
        );

        $this->assertSame(
            0,
            $this->detect(),
        );
    }

    private function createAdmin(): void
    {
        User::factory()->create([
            'email' => 'khashayarshivaee@gmail.com',
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

    private function recordUsage(
        VpnUser $vpnUser,
        int $totalBytes,
    ): void {
        DB::table('vpn_user_daily_usages')
            ->insert([
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
        DB::table('vpn_user_daily_usages')
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
        DB::table('vpn_usage_alert_baselines')
            ->insert([
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
            VpnUsageAlertService::class,
        )->detect();
    }
}
