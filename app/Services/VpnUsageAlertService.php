<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendVpnUsageAlertPush;
use App\Models\PushDevice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class VpnUsageAlertService
{
    private const ADMIN_EMAIL =
        'khashayarshivaee@gmail.com';

    private const BYTES_PER_GB =
        1024 * 1024 * 1024;

    private const THRESHOLDS_GB = [
        3,
        5,
    ];

    /**
     * Detect daily VPN usage thresholds
     * and queue pending push notifications.
     *
     * Returns newly created alert count.
     */
    public function detect(): int
    {
        $today = CarbonImmutable::today('UTC')
            ->toDateString();

        $recipient = User::query()
            ->where('email', self::ADMIN_EMAIL)
            ->where('is_active', true)
            ->first(['id']);

        if ($recipient === null) {
            return 0;
        }

        $dailyUsages = DB::table(
            'vpn_user_daily_usages as usage',
        )
            ->join(
                'vpn_users as vpn',
                'vpn.id',
                '=',
                'usage.vpn_user_id',
            )
            ->where('usage.usage_date', $today)
            ->select([
                'usage.vpn_user_id',
                'usage.downlink_bytes',
                'usage.uplink_bytes',
                'vpn.name as vpn_user_name',
            ])
            ->get();

        /*
         * Read the initial consumption
         * recorded during deployment.
         *
         * Thresholds already reached
         * at activation must not
         * generate historical alerts.
         */
        $baselines = DB::table(
            'vpn_usage_alert_baselines',
        )
            ->where('usage_date', $today)
            ->pluck(
                'initial_total_bytes',
                'vpn_user_id',
            );

        $createdCount = 0;

        foreach ($dailyUsages as $usage) {
            $vpnUserId =
                (int) $usage->vpn_user_id;

            $totalBytes =
                (int) $usage->downlink_bytes
                + (int) $usage->uplink_bytes;

            $initialBytes = $baselines->has(
                $vpnUserId,
            )
                ? (int) $baselines->get(
                    $vpnUserId,
                )
                : null;

            foreach (
                self::THRESHOLDS_GB as $thresholdGb
            ) {
                $thresholdBytes =
                    $thresholdGb * self::BYTES_PER_GB;

                /*
                 * Threshold not reached yet.
                 */
                if ($totalBytes < $thresholdBytes) {
                    continue;
                }

                /*
                 * Threshold was already reached
                 * before alerts were activated.
                 *
                 * Example:
                 * Initial: 4 GB
                 *
                 * 3 GB => skip
                 * 5 GB => still eligible
                 */
                if (
                    $initialBytes !== null &&
                    $initialBytes >= $thresholdBytes
                ) {
                    continue;
                }

                $now = CarbonImmutable::now('UTC');

                /*
                 * One alert per user, date,
                 * and threshold.
                 */
                $inserted = DB::table(
                    'vpn_usage_alerts',
                )->insertOrIgnore([
                    'vpn_user_id' => $vpnUserId,

                    'recipient_user_id' =>
                        (int) $recipient->id,

                    'vpn_user_name' =>
                        (string) $usage->vpn_user_name,

                    'usage_date' => $today,

                    'threshold_gb' => $thresholdGb,

                    'total_bytes' => $totalBytes,

                    'read_at' => null,

                    'push_sent_at' => null,

                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $createdCount += $inserted;
            }
        }

        /*
         * Dispatch new and previously
         * pending alerts.
         */
        $this->dispatchPendingPushes(
            $today,
            (int) $recipient->id,
        );

        return $createdCount;
    }

    private function dispatchPendingPushes(
        string $today,
        int $recipientUserId,
    ): void {
        /*
         * No active iPhone:
         * keep alerts pending.
         */
        $hasActiveDevice = PushDevice::query()
            ->where(
                'user_id',
                $recipientUserId,
            )
            ->where('platform', 'ios')
            ->where('is_active', true)
            ->exists();

        if (!$hasActiveDevice) {
            return;
        }

        /*
         * Only today's pending alerts
         * for the authorized admin.
         */
        $alertIds = DB::table(
            'vpn_usage_alerts',
        )
            ->where(
                'recipient_user_id',
                $recipientUserId,
            )
            ->where('usage_date', $today)
            ->whereNull('push_sent_at')
            ->orderBy('id')
            ->pluck('id');

        foreach ($alertIds as $alertId) {
            SendVpnUsageAlertPush::dispatch(
                (int) $alertId,
            )->afterCommit();
        }
    }
}
