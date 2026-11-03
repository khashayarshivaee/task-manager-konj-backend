<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MonitoredServer;
use App\Models\MonitoredServerSample;
use Carbon\CarbonImmutable;
use RuntimeException;

final class MonitoredServerSampleRecorderService
{
    public function record(
        MonitoredServer $server,
        array $health,
    ): void {
        // Validate percentage measurements.
        foreach ([
                     'cpu_percent',
                     'memory_percent',
                     'disk_percent',
                 ] as $field) {
            $value = $health[$field] ?? null;

            if (
                !is_numeric($value) ||
                !is_finite((float) $value) ||
                (float) $value < 0 ||
                (float) $value > 100
            ) {
                throw new RuntimeException(
                    "Invalid monitoring value: {$field}",
                );
            }
        }

        // Validate byte counts and uptime.
        foreach ([
                     'memory_total_bytes',
                     'memory_used_bytes',
                     'disk_total_bytes',
                     'disk_used_bytes',
                     'uptime_seconds',
                 ] as $field) {
            $value = $health[$field] ?? null;

            if (
                !is_int($value) ||
                $value < 0
            ) {
                throw new RuntimeException(
                    "Invalid monitoring value: {$field}",
                );
            }
        }

        if (
            $health['memory_total_bytes'] === 0 ||
            $health['disk_total_bytes'] === 0 ||
            $health['memory_used_bytes'] >
            $health['memory_total_bytes'] ||
            $health['disk_used_bytes'] >
            $health['disk_total_bytes']
        ) {
            throw new RuntimeException(
                'Invalid monitoring resource measurements.',
            );
        }

        $timestamp = $health['measured_at'] ?? null;

        if (
            !is_string($timestamp) ||
            trim($timestamp) === ''
        ) {
            throw new RuntimeException(
                'Missing monitoring timestamp.',
            );
        }

        $measuredAt = CarbonImmutable::parse(
            $timestamp,
        )->utc();

        $now = CarbonImmutable::now('UTC');

        if (
            $measuredAt->lessThan($now->subMinutes(5)) ||
            $measuredAt->greaterThan($now->addSeconds(60))
        ) {
            throw new RuntimeException(
                'Monitoring measurement is outdated.',
            );
        }

        // Prevent duplicate samples for the same
        // server and measurement timestamp.
        MonitoredServerSample::query()->firstOrCreate(
            [
                'monitored_server_id' => $server->id,
                'measured_at' =>
                    $measuredAt->format('Y-m-d H:i:s'),
            ],
            [
                'cpu_percent' =>
                    (float) $health['cpu_percent'],

                'memory_percent' =>
                    (float) $health['memory_percent'],

                'memory_used_bytes' =>
                    $health['memory_used_bytes'],

                'memory_total_bytes' =>
                    $health['memory_total_bytes'],

                'disk_percent' =>
                    (float) $health['disk_percent'],

                'disk_used_bytes' =>
                    $health['disk_used_bytes'],

                'disk_total_bytes' =>
                    $health['disk_total_bytes'],

                'uptime_seconds' =>
                    $health['uptime_seconds'],
            ],
        );
    }
}
