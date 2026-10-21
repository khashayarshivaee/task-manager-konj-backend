<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

final class CollectServerSpeedTest extends Command
{
    protected $signature =
        'server-status:collect-speed-test';

    protected $description =
        'Measure and store server internet speed.';

    public function handle(): int
    {
        // Prevent simultaneous server speed tests.
        $lock = Cache::lock(
            'server-status:speed-test:lock',
            180,
        );

        if (!$lock->get()) {
            $this->warn(
                'Another server speed test is running.',
            );

            return self::SUCCESS;
        }

        try {
            $this->info(
                'Measuring server internet speed...',
            );

            $result = Process::timeout(150)->run([
                'speedtest-cli',
                '--secure',
                '--json',
            ]);

            if ($result->failed()) {
                throw new RuntimeException(
                    'Speedtest command failed.',
                );
            }

            $data = json_decode(
                $result->output(),
                true,
            );

            if (!is_array($data)) {
                throw new RuntimeException(
                    'Invalid Speedtest JSON response.',
                );
            }

            $download = $data['download'] ?? null;
            $upload = $data['upload'] ?? null;
            $ping = $data['ping'] ?? null;

            if (
                !is_numeric($download) ||
                !is_numeric($upload) ||
                (float) $download <= 0 ||
                (float) $upload <= 0
            ) {
                throw new RuntimeException(
                    'Invalid speed measurement.',
                );
            }

            // Discard impossible or failed latency readings.
            $pingMs = (
                is_numeric($ping) &&
                (float) $ping >= 0 &&
                (float) $ping < 3000
            )
                ? round((float) $ping, 1)
                : null;

            $measurement = [
                'download_mbps' => round(
                    (float) $download / 1_000_000,
                    2,
                ),

                'upload_mbps' => round(
                    (float) $upload / 1_000_000,
                    2,
                ),

                'ping_ms' => $pingMs,

                // speedtest-cli does not report jitter.
                'jitter_ms' => null,

                'server_name' => (string) (
                    $data['server']['name'] ?? 'Unknown'
                ),

                'measured_at' =>
                    CarbonImmutable::now('UTC')
                        ->toIso8601String(),
            ];

            // Shared latest result for all users.
            Cache::forever(
                'server-status:speed-test:latest',
                $measurement,
            );

            $this->info(
                'Server speed test completed.',
            );

            $this->table(
                ['Metric', 'Value'],
                [
                    [
                        'Download',
                        $measurement['download_mbps'] .
                        ' Mbps',
                    ],
                    [
                        'Upload',
                        $measurement['upload_mbps'] .
                        ' Mbps',
                    ],
                    [
                        'Ping',
                        $pingMs === null
                            ? 'Unavailable'
                            : $pingMs . ' ms',
                    ],
                ],
            );

            return self::SUCCESS;

        } catch (Throwable $exception) {
            report($exception);

            $this->error(
                'Unable to complete server speed test.',
            );

            return self::FAILURE;

        } finally {
            $lock->release();
        }
    }
}
