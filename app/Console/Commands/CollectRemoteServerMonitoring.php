<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MonitoredServer;
use App\Services\MonitoredServerHealthService;
use App\Services\MonitoredServerServicesStatusService;
use App\Services\RemoteServerMonitoringReaderService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

final class CollectRemoteServerMonitoring extends Command
{
    protected $signature =
        'server-status:collect-remote {server=germany}';

    protected $description =
        'Collect remote server health and services.';

    public function handle(
        RemoteServerMonitoringReaderService $reader,
        MonitoredServerHealthService $healthService,
        MonitoredServerServicesStatusService $servicesService,
    ): int {
        $slug = (string) $this->argument('server');

        try {
            $server = MonitoredServer::query()
                ->where('slug', $slug)
                ->where('driver', 'ssh')
                ->where('is_enabled', true)
                ->first();

            if (!$server) {
                throw new RuntimeException(
                    'Remote monitoring server is unavailable.',
                );
            }

            // The reader verifies the SSH configuration
            // and whether remote monitoring is enabled.
            $snapshot = $reader->read($slug);

            $health = $snapshot['health'] ?? null;
            $services = $snapshot['services'] ?? null;

            if (
                !is_array($health) ||
                !is_array($services) ||
                !is_array($services['services'] ?? null) ||
                !$this->isFresh($health['measured_at'] ?? null) ||
                !$this->isFresh($services['checked_at'] ?? null)
            ) {
                throw new RuntimeException(
                    'Invalid or outdated monitoring snapshot.',
                );
            }

            foreach ([
                         'cpu_percent',
                         'memory_percent',
                         'disk_percent',
                     ] as $field) {
                $value = $health[$field] ?? null;

                if (
                    !is_numeric($value) ||
                    (float) $value < 0 ||
                    (float) $value > 100
                ) {
                    throw new RuntimeException(
                        'Invalid health measurement.',
                    );
                }
            }

            $expiresAt = now()->addMinutes(3);

            Cache::put(
                $healthService->cacheKey($server),
                $health,
                $expiresAt,
            );

            Cache::put(
                $servicesService->cacheKey($server),
                $services,
                $expiresAt,
            );

            $this->info(
                "Monitoring snapshot stored for {$slug}.",
            );

            return self::SUCCESS;

        } catch (Throwable $exception) {
            report($exception);

            $this->error(
                'Remote monitoring collection failed.',
            );

            return self::FAILURE;
        }
    }

    private function isFresh(mixed $timestamp): bool
    {
        if (
            !is_string($timestamp) ||
            trim($timestamp) === ''
        ) {
            return false;
        }

        try {
            $measured = CarbonImmutable::parse(
                $timestamp,
            )->utc();
        } catch (Throwable) {
            return false;
        }

        $now = CarbonImmutable::now('UTC');

        return $measured->greaterThanOrEqualTo(
                $now->subMinutes(3),
            ) && $measured->lessThanOrEqualTo(
                $now->addSeconds(60),
            );
    }
}
