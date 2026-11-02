<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Process;
use JsonException;
use RuntimeException;

final class RemoteServerMonitoringReaderService
{
    private const MAX_RESPONSE_BYTES = 65536;

    public function read(string $slug): array
    {
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            throw new RuntimeException(
                'Invalid monitoring server.',
            );
        }

        $config = config(
            "server_monitoring.remotes.{$slug}",
        );

        if (!is_array($config)) {
            throw new RuntimeException(
                'Monitoring server is not configured.',
            );
        }

        if (!($config['enabled'] ?? false)) {
            throw new RuntimeException(
                'Remote monitoring is disabled.',
            );
        }

        $host = (string) ($config['host'] ?? '');
        $user = (string) ($config['user'] ?? '');
        $port = (int) ($config['port'] ?? 22);

        $key = (string) (
            $config['identity_file'] ?? ''
        );

        $knownHosts = (string) (
            $config['known_hosts_file'] ?? ''
        );

        $remoteCommand = (string) (
            $config['remote_command'] ?? ''
        );

        $connectTimeout = (int) (
            $config['connect_timeout'] ?? 8
        );

        $processTimeout = (int) (
            $config['process_timeout'] ?? 20
        );

        if (
            !preg_match(
                '/^[a-zA-Z0-9][a-zA-Z0-9.-]*$/',
                $host,
            ) ||
            !preg_match('/^[a-z_][a-z0-9_-]*$/', $user) ||
            $port < 1 ||
            $port > 65535 ||
            !str_starts_with($key, '/') ||
            !str_starts_with($knownHosts, '/') ||
            $remoteCommand !== 'snapshot' ||
            $connectTimeout < 1 ||
            $connectTimeout > 30 ||
            $processTimeout < $connectTimeout ||
            $processTimeout > 60
        ) {
            throw new RuntimeException(
                'Invalid remote monitoring configuration.',
            );
        }

        $command = [
            '/usr/bin/ssh',
            '-T',

            '-i',
            $key,

            '-p',
            (string) $port,

            '-o',
            'IdentitiesOnly=yes',

            '-o',
            'BatchMode=yes',

            '-o',
            'PasswordAuthentication=no',

            '-o',
            'KbdInteractiveAuthentication=no',

            '-o',
            'StrictHostKeyChecking=yes',

            '-o',
            'UserKnownHostsFile=' . $knownHosts,

            '-o',
            'ClearAllForwardings=yes',

            '-o',
            'ConnectionAttempts=1',

            '-o',
            'ConnectTimeout=' . $connectTimeout,

            '-o',
            'LogLevel=ERROR',

            $user . '@' . $host,

            $remoteCommand,
        ];

        $result = Process::env([
            'HOME' => '/tmp',
            'XDG_CONFIG_HOME' => '/tmp',
        ])
            ->timeout($processTimeout)
            ->run($command);

        if ($result->failed()) {
            throw new RuntimeException(
                'Unable to collect remote server monitoring data.',
            );
        }

        $output = trim($result->output());

        if (
            $output === '' ||
            strlen($output) > self::MAX_RESPONSE_BYTES
        ) {
            throw new RuntimeException(
                'Invalid remote monitoring response size.',
            );
        }

        try {
            $payload = json_decode(
                $output,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Invalid remote monitoring JSON.',
                previous: $exception,
            );
        }

        if (
            !is_array($payload) ||
            ($payload['schema_version'] ?? null) !== 1 ||
            !is_array($payload['health'] ?? null) ||
            !is_array($payload['services'] ?? null) ||
            !is_array(
                $payload['services']['services'] ?? null
            ) ||
            !is_string(
                $payload['health']['measured_at'] ?? null
            ) ||
            !is_string(
                $payload['services']['checked_at'] ?? null
            )
        ) {
            throw new RuntimeException(
                'Invalid remote monitoring payload.',
            );
        }

        return [
            'health' => $payload['health'],
            'services' => $payload['services'],
        ];
    }
}
