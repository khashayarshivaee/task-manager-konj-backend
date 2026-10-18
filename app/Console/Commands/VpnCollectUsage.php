<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\VpnUsageAlertService;
use App\Services\VpnUsageCollectorService;
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

        /*
         * Step 2:
         * Detect 3 GB and 5 GB thresholds
         * from the stored daily usage.
         *
         * Run even if collection failed
         * for some users, so successfully
         * collected users are still checked.
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

            return self::FAILURE;
        }

        if ($result['failed'] > 0) {
            $this->error(
                'Some VPN users could not be processed.',
            );

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
