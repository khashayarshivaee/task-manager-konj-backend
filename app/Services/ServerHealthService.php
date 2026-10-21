<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use RuntimeException;

final class ServerHealthService
{
    public function collect(): array
    {
        // Measure CPU usage over a short interval.
        $cpuBefore = $this->readCpuTimes();

        usleep(250_000);

        $cpuAfter = $this->readCpuTimes();

        $totalDelta =
            $cpuAfter['total'] - $cpuBefore['total'];

        $idleDelta =
            $cpuAfter['idle'] - $cpuBefore['idle'];

        $cpuPercent = $totalDelta > 0
            ? round(
                max(
                    0,
                    min(
                        100,
                        (1 - $idleDelta / $totalDelta) * 100
                    )
                ),
                1
            )
            : 0.0;

        // Memory usage.
        $memory = $this->readMemory();

        $memoryUsed = max(
            0,
            $memory['total'] - $memory['available']
        );

        // Root filesystem usage.
        $diskTotal = disk_total_space('/');
        $diskFree = disk_free_space('/');

        if (
            $diskTotal === false ||
            $diskFree === false ||
            $diskTotal <= 0
        ) {
            throw new RuntimeException(
                'Unable to read disk usage.'
            );
        }

        $diskUsed = max(
            0,
            $diskTotal - $diskFree
        );

        return [
            'cpu_percent' => $cpuPercent,

            'memory_total_bytes' =>
                $memory['total'],

            'memory_used_bytes' =>
                $memoryUsed,

            'memory_percent' => round(
                $memoryUsed / $memory['total'] * 100,
                1
            ),

            'disk_total_bytes' =>
                (int) $diskTotal,

            'disk_used_bytes' =>
                (int) $diskUsed,

            'disk_percent' => round(
                $diskUsed / $diskTotal * 100,
                1
            ),

            'uptime_seconds' =>
                $this->readUptime(),

            'measured_at' =>
                CarbonImmutable::now('UTC')
                    ->toIso8601String(),
        ];
    }

    private function readCpuTimes(): array
    {
        $content = @file_get_contents('/proc/stat');

        if (
            $content === false ||
            !preg_match(
                '/^cpu[ \t]+([0-9 \t]+)$/m',
                $content,
                $matches
            )
        ) {
            throw new RuntimeException(
                'Unable to read CPU statistics.'
            );
        }

        $parts = preg_split(
            '/\s+/',
            trim($matches[1])
        );

        if (!$parts || count($parts) < 5) {
            throw new RuntimeException(
                'Invalid CPU statistics.'
            );
        }

        $values = array_map('intval', $parts);

        return [
            'total' => array_sum($values),

            'idle' =>
                $values[3] + $values[4],
        ];
    }

    private function readMemory(): array
    {
        $content = @file_get_contents(
            '/proc/meminfo'
        );

        if ($content === false) {
            throw new RuntimeException(
                'Unable to read memory statistics.'
            );
        }

        $values = [];

        foreach (['MemTotal', 'MemAvailable'] as $key) {
            if (!preg_match(
                '/^' . $key . ':\s+(\d+)\s+kB$/m',
                $content,
                $matches
            )) {
                throw new RuntimeException(
                    'Missing memory statistic: ' . $key
                );
            }

            $values[$key] =
                (int) $matches[1] * 1024;
        }

        if ($values['MemTotal'] <= 0) {
            throw new RuntimeException(
                'Invalid total memory.'
            );
        }

        return [
            'total' => $values['MemTotal'],

            'available' => min(
                $values['MemTotal'],
                $values['MemAvailable']
            ),
        ];
    }

    private function readUptime(): int
    {
        $content = @file_get_contents(
            '/proc/uptime'
        );

        if ($content === false) {
            throw new RuntimeException(
                'Unable to read server uptime.'
            );
        }

        $parts = preg_split(
            '/\s+/',
            trim($content)
        );

        if (
            !$parts ||
            !is_numeric($parts[0] ?? null)
        ) {
            throw new RuntimeException(
                'Invalid server uptime.'
            );
        }

        return max(
            0,
            (int) floor((float) $parts[0])
        );
    }
}
