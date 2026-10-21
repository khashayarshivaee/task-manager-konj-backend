<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Process;

final class ServerServicesStatusService
{
    private const SYSTEMD_SERVICES = [
        'xray' => [
            'name' => 'Xray VPN V2',
            'unit' => 'xray-konj-v2.service',
        ],
        'nginx' => [
            'name' => 'Nginx',
            'unit' => 'nginx.service',
        ],
        'mysql' => [
            'name' => 'MySQL',
            'unit' => 'mysql.service',
        ],
        'supervisor' => [
            'name' => 'Supervisor',
            'unit' => 'supervisor.service',
        ],
        'cron' => [
            'name' => 'Cron',
            'unit' => 'cron.service',
        ],
    ];

    private const SUPERVISOR_PROGRAMS = [
        'reverb' => [
            'name' => 'Laravel Reverb',
            'program' => 'task-manager-reverb',
        ],
        'queue' => [
            'name' => 'Laravel Queue',
            'program' =>
                'task-manager-worker:task-manager-worker_00',
        ],
    ];

    public function collect(): array
    {
        $services = [];

        foreach (
            self::SYSTEMD_SERVICES as $key => $config
        ) {
            $services[] = $this->checkSystemd(
                $key,
                $config['name'],
                $config['unit'],
            );
        }

        $supervisorStates =
            $this->readSupervisorStates();

        foreach (
            self::SUPERVISOR_PROGRAMS as $key => $config
        ) {
            $program = $config['program'];

            $service = $supervisorStates[$program]
                ?? [
                    'status' => 'unknown',
                    'uptime_seconds' => null,
                ];

            $services[] = [
                'key' => $key,
                'name' => $config['name'],
                'status' => $service['status'],
                'uptime_seconds' =>
                    $service['uptime_seconds'],
            ];
        }

        return [
            'services' => $services,
            'checked_at' =>
                CarbonImmutable::now('UTC')
                    ->toIso8601String(),
        ];
    }

    private function checkSystemd(
        string $key,
        string $name,
        string $unit,
    ): array {
        $status = 'unknown';
        $uptime = null;

        try {
            $result = Process::timeout(5)->run([
                'systemctl',
                'show',
                $unit,
                '--property=ActiveState',
                '--property=ActiveEnterTimestampMonotonic',
            ]);

            if ($result->successful()) {
                $output = $result->output();

                if (preg_match(
                    '/^ActiveState=(\S+)$/m',
                    $output,
                    $matches,
                )) {
                    $status = match ($matches[1]) {
                        'active' => 'active',
                        'inactive', 'failed' => 'down',
                        default => 'unknown',
                    };
                }

                if (
                    $status === 'active' &&
                    preg_match(
                        '/^ActiveEnterTimestampMonotonic=(\d+)$/m',
                        $output,
                        $matches,
                    )
                ) {
                    $uptime = $this->systemdUptime(
                        (int) $matches[1],
                    );
                }
            }
        } catch (\Throwable) {
            $status = 'unknown';
        }

        return [
            'key' => $key,
            'name' => $name,
            'status' => $status,
            'uptime_seconds' => $uptime,
        ];
    }

    private function systemdUptime(
        int $startedMicroseconds,
    ): ?int {
        if ($startedMicroseconds <= 0) {
            return null;
        }

        $content = @file_get_contents(
            '/proc/uptime',
        );

        if ($content === false) {
            return null;
        }

        $parts = preg_split(
            '/\s+/',
            trim($content),
        );

        if (
            !$parts ||
            !is_numeric($parts[0] ?? null)
        ) {
            return null;
        }

        $bootUptime = (float) $parts[0];

        $startedAfterBoot =
            $startedMicroseconds / 1_000_000;

        return max(
            0,
            (int) floor(
                $bootUptime - $startedAfterBoot,
            ),
        );
    }

    private function readSupervisorStates(): array
    {
        try {
            $result = Process::timeout(5)->run([
                'supervisorctl',
                'status',
            ]);

            if ($result->failed()) {
                return [];
            }

            $states = [];

            foreach (
                preg_split('/\r?\n/', $result->output())
                as $line
            ) {
                if (!preg_match(
                    '/^(\S+)\s+(\S+)/',
                    trim($line),
                    $matches,
                )) {
                    continue;
                }

                $program = $matches[1];
                $rawState = strtoupper($matches[2]);

                $status = match ($rawState) {
                    'RUNNING' => 'active',

                    'STOPPED',
                    'EXITED',
                    'FATAL' => 'down',

                    default => 'unknown',
                };

                $uptime = null;

                if (
                    $status === 'active' &&
                    preg_match(
                        '/\buptime\s+(\d+):(\d{2}):(\d{2})/',
                        $line,
                        $time,
                    )
                ) {
                    $uptime =
                        ((int) $time[1] * 3600) +
                        ((int) $time[2] * 60) +
                        (int) $time[3];
                }

                $states[$program] = [
                    'status' => $status,
                    'uptime_seconds' => $uptime,
                ];
            }

            return $states;

        } catch (\Throwable) {
            return [];
        }
    }
}
