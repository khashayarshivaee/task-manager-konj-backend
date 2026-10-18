<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\VpnUsageAlertService;
use App\Services\VpnUsageCollectorService;
use App\Services\VpnTelegramUsageNotificationService;
use Illuminate\Console\Command;
use Throwable;

class VpnCollectUsage extends Command
{
    protected $signature =
        'vpn:xray-collect-usage';

    protected $description =
        'Collect daily VPN usage and detect usage alerts.';

    public function handle(
        VpnUsageCollectorService $collector,
        VpnUsageAlertService $alertService,
        VpnTelegramUsageNotificationService $telegramService,
    ): int {
        /*
         * Step 1:
         * Collect VPN traffic from Xray.
         */
        $result = $collector->collect();

        $this->info(
            sprintf(
                'VPN usage collection: %d baselined, %d collected, %d skipped, %d failed.',
                $result['baselined'],
                $result['collected'],
                $result['skipped'],
                $result['failed'],
            ),
        );

        $alertDetectionFailed = false;

        /*
         * Step 2:
         * Existing admin notifications.
         *
         * Keep the original APNs
         * notification behavior.
         */
        try {
            $createdAlerts =
                $alertService->detect();

            $this->info(
                sprintf(
                    'VPN usage alerts: %d new alert(s).',
                    $createdAlerts,
                ),
            );
        } catch (Throwable $exception) {
            report($exception);

            $this->error(
                'VPN usage alert detection failed.',
            );

            $alertDetectionFailed = true;
        }

        /*
         * Step 3:
         * KONJ TEAM Telegram notifications.
         *
         * Runs independently from
         * admin push notifications.
         *
         * Telegram delivery is handled
         * asynchronously by the queue.
         */
        try {
            $createdTelegramNotifications =
                $telegramService->detect();

            $this->info(
                sprintf(
                    'VPN Telegram notifications: %d new notification(s).',
                    $createdTelegramNotifications,
                ),
            );
        } catch (Throwable $exception) {
            /*
             * A Telegram problem must
             * not stop VPN collection
             * or the existing APNs logic.
             *
             * Log the problem and retry
             * detection on the next run.
             */
            report($exception);

            $this->warn(
                'VPN Telegram notification detection failed.',
            );
        }

        /*
         * Preserve the original command
         * failure behavior for collector
         * and admin alert failures.
         */
        if ($result['failed'] > 0) {
            $this->error(
                'Some VPN users could not be processed.',
            );

            return self::FAILURE;
        }

        if ($alertDetectionFailed) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
