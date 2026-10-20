<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(
        Inspiring::quote(),
    );
})
    ->purpose(
        'Display an inspiring quote',
    );

/*
 * Collect VPN user destinations.
 *
 * The log path and cursor source are
 * configurable for each Xray instance.
 */
Schedule::command(
    'vpn:xray-ingest-destinations',
    [
        '--file' => config(
            'xray.access_log',
            '/var/log/xray/access.log',
        ),

        '--source' => config(
            'xray.access_log_source',
            'xray-access',
        ),
    ],
)
    ->everyMinute()
    ->withoutOverlapping();

/*
 * Collect daily VPN traffic usage.
 *
 * Runs independently from the
 * Xray destinations ingestion.
 */
Schedule::command(
    'vpn:xray-collect-usage',
)
    ->everyMinute()
    ->withoutOverlapping();
