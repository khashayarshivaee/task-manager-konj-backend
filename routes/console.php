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

Schedule::command(
    'vpn:xray-ingest-destinations',
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

