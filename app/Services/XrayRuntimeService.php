<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Process;
use JsonException;
use RuntimeException;

final class XrayRuntimeService
{
    public function getUptimeSeconds(): int
    {
        $binary = trim(
            (string) config('xray.binary'),
        );

        $apiServer = trim(
            (string) config('xray.api_server'),
        );

        if ($binary === '' || $apiServer === '') {
            throw new RuntimeException(
                'Xray runtime configuration is missing.',
            );
        }

        $result = Process::timeout(10)->run([
            $binary,
            'api',
            'statssys',
            '--server='.$apiServer,
        ]);

        if ($result->failed()) {
            throw new RuntimeException(
                'Unable to read Xray runtime information.',
            );
        }

        try {
            $payload = json_decode(
                $result->output(),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Xray returned invalid runtime information.',
                previous: $exception,
            );
        }

        if (
            ! is_array($payload) ||
            ! isset($payload['Uptime']) ||
            ! is_int($payload['Uptime']) ||
            $payload['Uptime'] < 0
        ) {
            throw new RuntimeException(
                'Xray returned an invalid uptime.',
            );
        }

        return $payload['Uptime'];
    }

    public function hasRestarted(
        int $previousUptimeSeconds,
        int $currentUptimeSeconds,
        int $elapsedSeconds,
    ): bool {
        if (
            $previousUptimeSeconds < 0 ||
            $currentUptimeSeconds < 0 ||
            $elapsedSeconds < 0
        ) {
            throw new RuntimeException(
                'Invalid Xray runtime comparison values.',
            );
        }

        $expectedUptime = $previousUptimeSeconds
            + $elapsedSeconds;

        // Allow small timing differences between samples.
        $toleranceSeconds = 5;

        return $currentUptimeSeconds + $toleranceSeconds
            < $expectedUptime;
    }

}
