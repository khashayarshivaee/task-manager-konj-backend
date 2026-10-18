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

class VpnUsageAlertServiceTest extends TestCase
{
    use RefreshDatabase;

    private const GB = 1024 * 1024 * 1024;

    public function test_alerts_at_three_and_five_gb(): void
    {
        $this->createAdmin();

        $vpnUser = $this->createVpnUser('Admin VPN');

        $this->recordUsage(
            $vpnUser,
            2 * self::GB,
            0,
        );

        // Below 3 GB: no alert.
        $this->assertSame(0, $this->detect());

        // Download + Upload reaches 3 GB.
        $this->updateUsage(
            $vpnUser,
            2 * self::GB,
            self::GB,
        );

        $this->assertSame(1, $this->detect());

        // Repeated checks must not duplicate alerts.
        $this->assertSame(0, $this->detect());

        // Total usage reaches 5 GB.
        $this->updateUsage(
            $vpnUser,
            4 * self::GB,
            self::GB,
        );

        $this->assertSame(1, $this->detect());

        $thresholds = DB::table('vpn_usage_alerts')
            ->where('vpn_user_id', $vpnUser->id)
            ->orderBy('threshold_gb')
            ->pluck('threshold_gb')
            ->map(fn ($value) => (int) $value)
            ->all();

        $this->assertSame([3, 5], $thresholds);

        $this->assertSame(0, $this->detect());
    }

    public function test_six_gb_creates_both_alerts(): void
    {
        $this->createAdmin();

        $vpnUser = $this->createVpnUser('iPhone');

        $this->recordUsage(
            $vpnUser,
            5 * self::GB,
            self::GB,
        );

        $this->assertSame(2, $this->detect());

        $this->assertSame(
            2,
            DB::table('vpn_usage_alerts')->count(),
        );

        $this->assertSame(0, $this->detect());
    }

    public function test_each_vpn_user_has_independent_alerts(): void
    {
        $this->createAdmin();

        $first = $this->createVpnUser('Niloofar');
        $second = $this->createVpnUser('ABBAS');

        $this->recordUsage(
            $first,
            3 * self::GB,
            0,
        );

        $this->recordUsage(
            $second,
            5 * self::GB,
            0,
        );

        $this->assertSame(3, $this->detect());

        $this->assertSame(
            1,
            DB::table('vpn_usage_alerts')
                ->where('vpn_user_id', $first->id)
                ->count(),
        );

        $this->assertSame(
            2,
            DB::table('vpn_usage_alerts')
                ->where('vpn_user_id', $second->id)
                ->count(),
        );
    }

    public function test_only_active_authorized_admin_receives_alerts(): void
    {
        User::factory()->create([
            'email' => 'other@example.test',
        ]);

        $vpnUser = $this->createVpnUser('Test VPN');

        $this->recordUsage(
            $vpnUser,
            4 * self::GB,
            0,
        );

        // No authorized admin.
        $this->assertSame(0, $this->detect());

        $admin = User::factory()->create([
            'email' => 'khashayarshivaee@gmail.com',
            'is_active' => false,
        ]);

        // Inactive admin.
        $this->assertSame(0, $this->detect());

        $admin->forceFill([
            'is_active' => true,
        ])->save();

        $this->assertSame(1, $this->detect());

        $this->assertDatabaseHas('vpn_usage_alerts', [
            'recipient_user_id' => $admin->id,
            'vpn_user_id' => $vpnUser->id,
            'threshold_gb' => 3,
        ]);
    }

    public function test_previous_day_usage_does_not_trigger_today(): void
    {
        $this->createAdmin();

        $vpnUser = $this->createVpnUser('Old Usage');

        $yesterday = CarbonImmutable::today('UTC')
            ->subDay()
            ->toDateString();

        $this->recordUsage(
            $vpnUser,
            10 * self::GB,
            0,
            $yesterday,
        );

        $this->assertSame(0, $this->detect());
    }

    public function test_alerts_reset_on_next_utc_day(): void
    {
        $this->travelTo(
            CarbonImmutable::parse(
                '2026-10-18 12:00:00',
                'UTC',
            ),
        );

        $this->createAdmin();

        $vpnUser = $this->createVpnUser('Daily VPN');

        $this->recordUsage(
            $vpnUser,
            5 * self::GB,
            0,
        );

        $this->assertSame(2, $this->detect());

        $this->travelTo(
            CarbonImmutable::parse(
                '2026-10-19 12:00:00',
                'UTC',
            ),
        );

        $this->recordUsage(
            $vpnUser,
            3 * self::GB,
            0,
        );

        $this->assertSame(1, $this->detect());

        $this->assertSame(
            3,
            DB::table('vpn_usage_alerts')->count(),
        );
    }

    private function createAdmin(): User
    {
        return User::factory()->create([
            'email' => 'khashayarshivaee@gmail.com',
        ]);
    }

    private function createVpnUser(
        string $name,
    ): VpnUser {
        return VpnUser::query()->create([
            'name' => $name,
            'uuid' => (string) Str::uuid(),
            'xray_email' =>
                Str::uuid() . '@example.test',
            'is_active' => true,
            'flow' => 'xtls-rprx-vision',
        ]);
    }

    private function recordUsage(
        VpnUser $vpnUser,
        int $download,
        int $upload,
        ?string $date = null,
    ): void {
        DB::table('vpn_user_daily_usages')
            ->insert([
                'vpn_user_id' => $vpnUser->id,
                'usage_date' =>
                    $date ?? CarbonImmutable::today('UTC')
                        ->toDateString(),
                'downlink_bytes' => $download,
                'uplink_bytes' => $upload,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function updateUsage(
        VpnUser $vpnUser,
        int $download,
        int $upload,
    ): void {
        DB::table('vpn_user_daily_usages')
            ->where('vpn_user_id', $vpnUser->id)
            ->where(
                'usage_date',
                CarbonImmutable::today('UTC')
                    ->toDateString(),
            )
            ->update([
                'downlink_bytes' => $download,
                'uplink_bytes' => $upload,
                'updated_at' => now(),
            ]);
    }

    private function detect(): int
    {
        return app(
            VpnUsageAlertService::class,
        )->detect();
    }
}
