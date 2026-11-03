<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MonitoredServer;
use App\Services\MonitoredServerSampleRecorderService;
use App\Services\RemoteServerMonitoringReaderService;
use App\Services\ServerHealthService;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

final class CollectMonitoredServerSamples extends Command
{
    protected $signature = 'server-status:collect-samples';

    protected $description =
        'Collect historical performance samples from monitored servers.';

    public function handle(
        ServerHealthService $localHealth,
        RemoteServerMonitoringReaderService $remoteReader,
        MonitoredServerSampleRecorderService $recorder,
    ): int {
        $servers = MonitoredServer::query()
            ->enabled()
            ->orderBy('sort_order')
            ->get();

        $recorded = 0;
        $failed = 0;

        foreach ($servers as $server) {

            // Skip remote servers whose SSH
            // monitoring is not enabled.
            if (
                $server->driver === 'ssh' &&
                !(bool) config(
                    "server_monitoring.remotes.{$server->slug}.enabled",
                    false,
                )
            ) {
                $this->line(
                    "Skipped disabled remote: {$server->slug}",
                );

                continue;
            }

            try {
                $health = match ($server->driver) {
                    'local' => $localHealth->collect(),

                    'ssh' => $remoteReader
                        ->read($server->slug)['health'],

                    default => throw new RuntimeException(
                        'Unsupported monitoring driver.',
                    ),
                };

                if (!is_array($health)) {
                    throw new RuntimeException(
                        'Invalid server health snapshot.',
                    );
                }

                $recorder->record(
                    $server,
                    $health,
                );

                $recorded++;

                $this->info(
                    "Sample recorded: {$server->slug}",
                );

            } catch (Throwable $exception) {
                $failed++;

                report($exception);

                $this->error(
                    "Collection failed: {$server->slug}",
                );
            }
        }

        $this->line(
            "Finished. Recorded: {$recorded}, Failed: {$failed}",
        );

        return $failed === 0
            ? self::SUCCESS
            : self::FAILURE;
    }
}
