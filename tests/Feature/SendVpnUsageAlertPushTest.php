<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendVpnUsageAlertPush;
use App\Models\PushDevice;
use App\Models\User;
use App\Models\VpnUser;
use App\Services\ApnsPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class SendVpnUsageAlertPushTest extends TestCase
{
    use RefreshDatabase;

    private const GB = 1024 * 1024 * 1024;

    public function test_successful_push_marks_alert_as_sent(): void
    {
        $admin = $this->createUser(
            'khashayarshivaee@gmail.com',
        );

        $device = $this->registerIphone($admin);

        $alertId = $this->createAlert($admin);

        $apns = Mockery::mock(ApnsPushService::class);

        $apns->shouldReceive('sendToDevice')
            ->once()
            ->withArgs(
                fn (
                    PushDevice $sentDevice,
                    string $title,
                    string $body,
                    array $data,
                ): bool =>
                    $sentDevice->id === $device->id
                    && str_contains($title, '3 GB')
                    && str_contains($body, 'Test VPN')
                    && $data['vpn_alert_id'] === $alertId,
            );

        $this->runJob($alertId, $apns);

        $this->assertNotNull(
            DB::table('vpn_usage_alerts')
                ->where('id', $alertId)
                ->value('push_sent_at'),
        );
    }

    public function test_failed_push_remains_pending(): void
    {
        $admin = $this->createUser(
            'khashayarshivaee@gmail.com',
        );

        $this->registerIphone($admin);

        $alertId = $this->createAlert($admin);

        $apns = Mockery::mock(ApnsPushService::class);

        $apns->shouldReceive('sendToDevice')
            ->once()
            ->andThrow(
                new RuntimeException(
                    'Simulated APNs failure',
                ),
            );

        try {
            $this->runJob($alertId, $apns);

            $this->fail(
                'Expected push delivery to fail.',
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'VPN alert push delivery failed.',
                $exception->getMessage(),
            );
        }

        $this->assertNull(
            DB::table('vpn_usage_alerts')
                ->where('id', $alertId)
                ->value('push_sent_at'),
        );
    }

    public function test_other_users_never_receive_vpn_push(): void
    {
        $otherUser = $this->createUser(
            'other@example.test',
        );

        $this->registerIphone($otherUser);

        $alertId = $this->createAlert($otherUser);

        $apns = Mockery::mock(ApnsPushService::class);

        $apns->shouldNotReceive('sendToDevice');

        $this->runJob($alertId, $apns);

        $this->assertNull(
            DB::table('vpn_usage_alerts')
                ->where('id', $alertId)
                ->value('push_sent_at'),
        );
    }

    public function test_sent_alert_is_not_sent_again(): void
    {
        $admin = $this->createUser(
            'khashayarshivaee@gmail.com',
        );

        $this->registerIphone($admin);

        $alertId = $this->createAlert($admin);

        DB::table('vpn_usage_alerts')
            ->where('id', $alertId)
            ->update([
                'push_sent_at' => now(),
            ]);

        $apns = Mockery::mock(ApnsPushService::class);

        $apns->shouldNotReceive('sendToDevice');

        $this->runJob($alertId, $apns);
    }

    private function createUser(string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'is_active' => true,
        ]);
    }

    private function registerIphone(
        User $user,
    ): PushDevice {
        return PushDevice::query()->create([
            'user_id' => $user->id,
            'token' => (string) Str::uuid(),
            'platform' => 'ios',
            'environment' => 'development',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);
    }

    private function createAlert(User $recipient): int
    {
        $vpnUser = VpnUser::query()->create([
            'name' => 'Test VPN',
            'uuid' => (string) Str::uuid(),
            'xray_email' =>
                Str::uuid() . '@example.test',
            'is_active' => true,
            'flow' => 'xtls-rprx-vision',
        ]);

        return (int) DB::table('vpn_usage_alerts')
            ->insertGetId([
                'vpn_user_id' => $vpnUser->id,
                'recipient_user_id' => $recipient->id,
                'vpn_user_name' => 'Test VPN',
                'usage_date' => now('UTC')
                    ->toDateString(),
                'threshold_gb' => 3,
                'total_bytes' => 3 * self::GB,
                'read_at' => null,
                'push_sent_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function runJob(
        int $alertId,
        ApnsPushService $apns,
    ): void {
        (new SendVpnUsageAlertPush(
            $alertId,
        ))->handle($apns);
    }
}
