<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Process;
use JsonException;
use RuntimeException;

class VpnSessionService
{
    private const READER_COMMAND =
        'sudo -n /usr/local/bin/task-manager-vpn-sessions';

    /**
     * @return array{
     *     total: int,
     *     summary: array<string, int>,
     *     sessions: array<int, array<string, mixed>>,
     *     connections: array<int, array<string, mixed>>
     * }
     */
    public function getActiveSessions(): array
    {
        $result = Process::timeout(5)
            ->run(self::READER_COMMAND);

        if ($result->failed()) {
            throw new RuntimeException(
                'Unable to read server connections.',
            );
        }

        try {
            $payload = json_decode(
                trim($result->output()),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Server connection reader returned invalid JSON.',
                previous: $exception,
            );
        }

        if (! is_array($payload)) {
            throw new RuntimeException(
                'Server connection reader returned an invalid payload.',
            );
        }

        $summary = $payload['summary'] ?? null;
        $sessions = $payload['sessions'] ?? null;
        $connections = $payload['connections'] ?? null;

        if (
            ! is_array($summary) ||
            ! is_array($sessions) ||
            ! is_array($connections)
        ) {
            throw new RuntimeException(
                'Server connection reader returned an invalid payload.',
            );
        }

        $normalizedSummary = [];

        foreach ($summary as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (! is_int($value) && ! is_numeric($value)) {
                continue;
            }

            $normalizedSummary[$key] = (int) $value;
        }

        $normalizedSessions = array_values(
            array_filter(
                $sessions,
                static fn (mixed $session): bool => is_array($session),
            ),
        );

        $normalizedConnections = array_values(
            array_filter(
                $connections,
                static fn (mixed $connection): bool => is_array($connection),
            ),
        );

        return [
            'total' => count($normalizedConnections),
            'summary' => $normalizedSummary,
            'sessions' => $normalizedSessions,
            'connections' => $normalizedConnections,
        ];
    }
}
