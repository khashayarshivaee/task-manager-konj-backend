<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\VpnUsageCollectorService;
use Illuminate\Console\Command;

class VpnCollectUsage extends Command
{
    protected $signature = 'vpn:xray-collect-usage';

    protected $description =
        'Collect daily VPN download and upload usage from Xray.';

    public function handle(
        VpnUsageCollectorService $collector,
    ): int {
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

        if ($result['failed'] > 0) {
            $this->error(
                'Some VPN users could not be processed.',
            );

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
