<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendVpnTelegramUsageNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class VpnTelegramUsageNotificationService
{
    private const BYTES_PER_GB =
        1024 * 1024 * 1024;

    private const THRESHOLDS_GB = [
        3,
        5,
    ];

    /**
     * Detect usage thresholds for
     * Telegram-connected VPN users.
     *
     * Returns the number of new records.
     */
    public function detect(): int
    {
        $today = CarbonImmutable::today('UTC')
            ->toDateString();

        /*
         * Load existing usage baselines.
         *
         * Consumption already reached
         * before activation must not
         * create old notifications.
         */
        $baselines = DB::table(
            'vpn_telegram_usage_baselines',
        )
            ->where('usage_date', $today)
            ->pluck(
                'initial_total_bytes',
                'vpn_user_id',
            );

        /*
         * Only active VPN users with
         * active Telegram connections.
         */
        $usages = DB::table(
            'vpn_user_daily_usages as usage',
        )
            ->join(
                'vpn_users as vpn',
                'vpn.id',
                '=',
                'usage.vpn_user_id',
            )
            ->join(
                'vpn_telegram_connections as telegram',
                'telegram.vpn_user_id',
                '=',
                'vpn.id',
            )
            ->where('usage.usage_date', $today)
            ->where('vpn.is_active', true)
            ->where('telegram.is_active', true)
            ->select([
                'usage.vpn_user_id',
                'usage.downlink_bytes',
                'usage.uplink_bytes',
                'telegram.telegram_chat_id',
            ])
            ->get();

        $createdCount = 0;

        foreach ($usages as $usage) {
            $vpnUserId =
                (int) $usage->vpn_user_id;

            $chatId =
                (int) $usage->telegram_chat_id;

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
                 * Not reached yet.
                 */
                if (
                    $totalBytes < $thresholdBytes
                ) {
                    continue;
                }

                /*
                 * Already reached before
                 * Telegram alerts were enabled.
                 */
                if (
                    $initialBytes !== null &&
                    $initialBytes >= $thresholdBytes
                ) {
                    continue;
                }

                $now =
                    CarbonImmutable::now('UTC');

                /*
                 * Unique database constraint:
                 *
                 * VPN user + UTC date + threshold.
                 *
                 * This prevents duplicate
                 * records during repeated
                 * Scheduler executions.
                 */
                $inserted = DB::table(
                    'vpn_telegram_usage_notifications',
                )->insertOrIgnore([
                    'vpn_user_id' => $vpnUserId,

                    'telegram_chat_id' => $chatId,

                    'usage_date' => $today,

                    'threshold_gb' => $thresholdGb,

                    'total_bytes' => $totalBytes,

                    'sent_at' => null,

                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $createdCount += $inserted;
            }
        }

        /*
         * Queue pending notifications
         * for currently connected users.
         */
        $this->dispatchPending($today);

        return $createdCount;
    }

    private function dispatchPending(
        string $today,
    ): void {
        /*
         * Match the stored recipient
         * against the current Telegram
         * connection before dispatching.
         *
         * The Job will validate again
         * immediately before sending.
         */
        $pendingIds = DB::table(
            'vpn_telegram_usage_notifications as notification',
        )
            ->join(
                'vpn_telegram_connections as telegram',
                function ($join): void {
                    $join->on(
                        'telegram.vpn_user_id',
                        '=',
                        'notification.vpn_user_id',
                    )->on(
                        'telegram.telegram_chat_id',
                        '=',
                        'notification.telegram_chat_id',
                    );
                },
            )
            ->join(
                'vpn_users as vpn',
                'vpn.id',
                '=',
                'notification.vpn_user_id',
            )
            ->where(
                'notification.usage_date',
                $today,
            )
            ->whereNull(
                'notification.sent_at',
            )
            ->where(
                'telegram.is_active',
                true,
            )
            ->where(
                'vpn.is_active',
                true,
            )
            ->orderBy(
                'notification.id',
            )
            ->pluck(
                'notification.id',
            );

        foreach ($pendingIds as $id) {
            SendVpnTelegramUsageNotification::dispatch(
                (int) $id,
            )->afterCommit();
        }
    }
}
