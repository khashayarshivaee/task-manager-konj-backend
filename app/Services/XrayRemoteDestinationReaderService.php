<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Process;
use JsonException;
use RuntimeException;

final class XrayRemoteDestinationReaderService
{
    private const MAX_CHUNK_BYTES = 262144;

    private const SSH_KEY =
        '/etc/task-manager/ssh/konj_xray_destinations_ro';

    private const KNOWN_HOSTS =
        '/etc/task-manager/ssh/known_hosts';

    private const REMOTE_SERVER =
        'deploy@116.202.1.136';

    /**
     * Read a chunk from the remote Xray access log.
     *
     * @return array{
     *     inode: int,
     *     size: int,
     *     start_offset: int,
     *     next_offset: int,
     *     reset: bool,
     *     data: string
     * }
     */
    public function read(
        int $offset,
        int $inode,
    ): array {
        if ($offset < 0 || $inode < 0) {
            throw new RuntimeException(
                'Invalid Xray log cursor.',
            );
        }

        $command = [
            '/usr/bin/ssh',
            '-T',

            '-i',
            self::SSH_KEY,

            '-o',
            'IdentitiesOnly=yes',

            '-o',
            'BatchMode=yes',

            '-o',
            'StrictHostKeyChecking=yes',

            '-o',
            'UserKnownHostsFile=' . self::KNOWN_HOSTS,

            '-o',
            'ConnectTimeout=8',

            '-o',
            'ConnectionAttempts=1',

            self::REMOTE_SERVER,

            sprintf(
                'read %d %d',
                $offset,
                $inode,
            ),
        ];

        $result = Process::env([
            'HOME' => '/tmp',
            'XDG_CONFIG_HOME' => '/tmp',
        ])
            ->timeout(20)
            ->run($command);

        if ($result->failed()) {
            throw new RuntimeException(
                'Unable to read remote Xray access log.',
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
                'Invalid remote Xray reader response.',
                previous: $exception,
            );
        }

        if (!is_array($payload)) {
            throw new RuntimeException(
                'Invalid remote Xray reader payload.',
            );
        }

        foreach ([
                     'inode',
                     'size',
                     'start_offset',
                     'next_offset',
                 ] as $key) {
            if (
                !isset($payload[$key]) ||
                !is_int($payload[$key]) ||
                $payload[$key] < 0
            ) {
                throw new RuntimeException(
                    'Invalid remote Xray log cursor.',
                );
            }
        }

        if (
            !isset($payload['reset']) ||
            !is_bool($payload['reset']) ||
            !isset($payload['data_base64']) ||
            !is_string($payload['data_base64'])
        ) {
            throw new RuntimeException(
                'Invalid remote Xray log data.',
            );
        }

        $data = base64_decode(
            $payload['data_base64'],
            true,
        );

        if (
            $data === false ||
            strlen($data) > self::MAX_CHUNK_BYTES
        ) {
            throw new RuntimeException(
                'Invalid remote Xray log chunk.',
            );
        }

        if (
            $data !== '' &&
            !str_ends_with($data, "\n")
        ) {
            throw new RuntimeException(
                'Incomplete remote Xray log chunk.',
            );
        }

        if (
            $payload['next_offset'] !==
            $payload['start_offset'] + strlen($data)
        ) {
            throw new RuntimeException(
                'Inconsistent remote Xray log offset.',
            );
        }

        if (
            $payload['reset'] &&
            $payload['start_offset'] !== 0
        ) {
            throw new RuntimeException(
                'Invalid remote Xray log reset.',
            );
        }

        if (
            !$payload['reset'] &&
            $payload['start_offset'] !== $offset
        ) {
            throw new RuntimeException(
                'Unexpected remote Xray log position.',
            );
        }

        return [
            'inode' => $payload['inode'],
            'size' => $payload['size'],
            'start_offset' =>
                $payload['start_offset'],
            'next_offset' =>
                $payload['next_offset'],
            'reset' => $payload['reset'],
            'data' => $data,
        ];
    }
}
