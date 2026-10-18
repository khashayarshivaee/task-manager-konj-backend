<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PushDevice;
use App\Models\User;
use App\Services\ApnsPushService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class SendVpnUsageAlertPush implements
    ShouldQueue,
    ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public int $uniqueFor = 600;

    private const ADMIN_EMAIL =
        'khashayarshivaee@gmail.com';

    private const GB = 1024 * 1024 * 1024;

    public function __construct(
        public readonly int $alertId,
    ) {
    }

    public function uniqueId(): string
    {
        return 'vpn-usage-alert-' . $this->alertId;
    }

    public function backoff(): array
    {
        return [60, 180, 300];
    }

    public function handle(
        ApnsPushService $apnsService,
    ): void {
        $alert = DB::table('vpn_usage_alerts')
            ->where('id', $this->alertId)
            ->first();

        if (
            $alert === null ||
            $alert->push_sent_at !== null
        ) {
            return;
        }

        /*
         * Security:
         * Only the authorized VPN administrator
         * can receive these notifications.
         */
        $admin = User::query()
            ->where(
                'id',
                $alert->recipient_user_id,
            )
            ->where('email', self::ADMIN_EMAIL)
            ->where('is_active', true)
            ->first();

        if ($admin === null) {
            return;
        }

        $devices = PushDevice::query()
            ->where('user_id', $admin->id)
            ->where('platform', 'ios')
            ->where('is_active', true)
            ->get();

        if ($devices->isEmpty()) {
            return;
        }

        $threshold = (int) $alert->threshold_gb;

        $totalGb =
            (int) $alert->total_bytes / self::GB;

        $vpnName = (string) $alert->vpn_user_name;

        $title = sprintf(
            'VPN Usage Alert - %d GB',
            $threshold,
        );

        $body = sprintf(
            '%s has used %.2f GB today and reached the %d GB threshold.',
            $vpnName,
            $totalGb,
            $threshold,
        );

        $data = [
            'type' => 'vpn_usage_alert',
            'vpn_alert_id' => (int) $alert->id,
            'vpn_user_id' => (int) $alert->vpn_user_id,
            'threshold_gb' => $threshold,
            'usage_date' => (string) $alert->usage_date,
        ];

        $successful = 0;

        foreach ($devices as $device) {
            try {
                $apnsService->sendToDevice(
                    $device,
                    $title,
                    $body,
                    $data,
                );

                $successful++;
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        /*
         * Keep the notification pending
         * if delivery failed on every device.
         */
        if ($successful === 0) {
            throw new RuntimeException(
                'VPN alert push delivery failed.',
            );
        }

        DB::table('vpn_usage_alerts')
            ->where('id', $this->alertId)
            ->whereNull('push_sent_at')
            ->update([
                'push_sent_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
