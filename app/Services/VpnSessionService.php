<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Process;
use JsonException;
use RuntimeException;

class VpnSessionService
{
    private const READER_COMMAND = 'sudo -n /usr/local/bin/task-manager-vpn-sessions';

    /**
     * @return array{
     *     total: int,
     *     sessions: array<int, array{
     *         pid: int,
     *         ssh_user: string,
     *         client_ip: string,
     *         client_port: int,
     *         elapsed_seconds: int,
     *         active_connections: int
     *     }>
     * }
     */
    public function getActiveSessions(): array
    {
        $result = Process::timeout(5)
            ->run(self::READER_COMMAND);

        if ($result->failed()) {
            throw new RuntimeException(
                'Unable to read VPN sessions.',
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
                'VPN session reader returned invalid JSON.',
                previous: $exception,
            );
        }

        if (
            ! is_array($payload) ||
            ! isset($payload['sessions']) ||
            ! is_array($payload['sessions'])
        ) {
            throw new RuntimeException(
                'VPN session reader returned an invalid payload.',
            );
        }

        $sessions = array_values(
            array_filter(
                $payload['sessions'],
                static fn (mixed $session): bool => is_array($session),
            ),
        );

        return [
            'total' => count($sessions),
            'sessions' => $sessions,
        ];
    }
}
