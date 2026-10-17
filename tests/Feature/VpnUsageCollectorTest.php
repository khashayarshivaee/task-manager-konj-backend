<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\VpnUser;
use App\Services\VpnUsageCollectorService;
use App\Services\XrayManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class VpnUsageCollectorTest extends TestCase
{
    use RefreshDatabase;

    private VpnUser $vpnUser;

    private string $invocationId;

    private array $samples = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->invocationId = str_repeat('a', 32);

        $this->vpnUser = VpnUser::query()->create([
            'name' => 'Usage Test',
            'uuid' => (string) Str::uuid(),
            'xray_email' => 'usage-test@example.test',
            'is_active' => true,
            'flow' => 'xtls-rprx-vision',
        ]);

        Process::fake(function () {
            return Process::result(
                $this->invocationId . "\n",
            );
        });

        $manager = Mockery::mock(
            XrayManagerService::class,
        );

        $manager
            ->shouldReceive('getUserStats')
            ->andReturnUsing(function () {
                return array_shift($this->samples);
            });

        $this->app->instance(
            XrayManagerService::class,
            $manager,
        );
    }

    public function test_baseline_and_normal_usage(): void
    {
        $first = $this->collectSample(1000, 2000);

        $this->assertSame(1, $first['baselined']);

        $this->assertSame(
            0,
            DB::table('vpn_user_daily_usages')->count(),
        );

        $second = $this->collectSample(1500, 2250);

        $this->assertSame(1, $second['collected']);

        $this->assertUsage(500, 250);

        $this->collectSample(1500, 2250);

        $this->assertUsage(500, 250);
    }

    public function test_xray_restart_uses_new_counters(): void
    {
        $this->collectSample(1000, 2000);

        $this->collectSample(1100, 2200);

        $this->assertUsage(100, 200);

        // Simulate a new Xray runtime.
        $this->invocationId = str_repeat('b', 32);

        $this->collectSample(30, 40);

        $this->assertUsage(130, 240);

        $this->collectSample(50, 70);

        $this->assertUsage(150, 270);
    }

    public function test_user_change_creates_new_baseline(): void
    {
        $this->collectSample(1000, 1000);

        $this->collectSample(1100, 1500);

        $this->assertUsage(100, 500);

        // Simulate a user state change.
        DB::table('vpn_users')
            ->where('id', $this->vpnUser->id)
            ->update([
                'updated_at' => now()
                    ->addMinutes(2)
                    ->format('Y-m-d H:i:s'),
            ]);

        $result = $this->collectSample(30, 40);

        $this->assertSame(1, $result['baselined']);

        // No historical traffic is counted again.
        $this->assertUsage(100, 500);

        $this->collectSample(50, 60);

        $this->assertUsage(120, 520);
    }

    public function test_unexpected_counter_decrease_is_skipped(): void
    {
        $this->collectSample(1000, 1000);

        $result = $this->collectSample(900, 900);

        $this->assertSame(1, $result['skipped']);

        $this->collectSample(1200, 1250);

        $this->assertUsage(200, 250);
    }


    public function test_collects_usage_separately_for_seven_users(): void
    {
        $users = [$this->vpnUser];

        for ($index = 2; $index <= 7; $index++) {
            $users[] = VpnUser::query()->create([
                'name' => "Usage Test {$index}",
                'uuid' => (string) Str::uuid(),
                'xray_email' => "usage-test-{$index}@example.test",
                'is_active' => true,
                'flow' => 'xtls-rprx-vision',
            ]);
        }

        // Establish an initial baseline for all users.
        foreach ($users as $user) {
            $this->samples[] = [
                'downlink_bytes' => 1000,
                'uplink_bytes' => 500,
                'total_bytes' => 1500,
            ];
        }

        $first = $this->app
            ->make(VpnUsageCollectorService::class)
            ->collect();

        $this->assertSame(7, $first['baselined']);

        // Simulate different usage for each user.
        foreach ($users as $index => $user) {
            $downlink = 1000 + (($index + 1) * 300);
            $uplink = 500 + (($index + 1) * 100);

            $this->samples[] = [
                'downlink_bytes' => $downlink,
                'uplink_bytes' => $uplink,
                'total_bytes' => $downlink + $uplink,
            ];
        }

        $second = $this->app
            ->make(VpnUsageCollectorService::class)
            ->collect();

        $this->assertSame(7, $second['collected']);

        foreach ($users as $index => $user) {
            $usage = DB::table('vpn_user_daily_usages')
                ->where('vpn_user_id', $user->id)
                ->first();

            $this->assertNotNull($usage);

            $this->assertSame(
                ($index + 1) * 300,
                (int) $usage->downlink_bytes,
            );

            $this->assertSame(
                ($index + 1) * 100,
                (int) $usage->uplink_bytes,
            );
        }

        $this->assertSame(
            7,
            DB::table('vpn_user_daily_usages')->count(),
        );
    }


    public function test_temporary_zero_stats_do_not_reset_usage(): void
    {
        // Establish initial baseline.
        $this->collectSample(1000, 500);

        // Record actual traffic.
        $this->collectSample(1400, 650);

        $this->assertUsage(400, 150);

        // Xray temporarily returns empty statistics.
        $result = $this->collectSample(0, 0);

        $this->assertSame(1, $result['skipped']);

        // Previously recorded usage must remain unchanged.
        $this->assertUsage(400, 150);

        // Xray statistics become available again.
        $result = $this->collectSample(1700, 800);

        $this->assertSame(1, $result['collected']);

        // Only the new traffic should be added.
        $this->assertUsage(700, 300);
    }



    private function collectSample(
        int $downlink,
        int $uplink,
    ): array {
        $this->samples[] = [
            'downlink_bytes' => $downlink,
            'uplink_bytes' => $uplink,
            'total_bytes' => $downlink + $uplink,
        ];

        return $this->app
            ->make(VpnUsageCollectorService::class)
            ->collect();
    }

    private function assertUsage(
        int $expectedDownload,
        int $expectedUpload,
    ): void {
        $record = DB::table('vpn_user_daily_usages')
            ->where('vpn_user_id', $this->vpnUser->id)
            ->first();

        $this->assertNotNull($record);

        $this->assertSame(
            $expectedDownload,
            (int) $record->downlink_bytes,
        );

        $this->assertSame(
            $expectedUpload,
            (int) $record->uplink_bytes,
        );
    }
}
