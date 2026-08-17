<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\VpnUser;
use Illuminate\Support\Facades\Process;
use JsonException;
use RuntimeException;

class XrayManagerService
{
    public function addUser(VpnUser $vpnUser): void
    {
        $payload = [
            'inbounds' => [
                [
                    'tag' => $this->inboundTag(),
                    'port' => $this->port(),
                    'protocol' => 'vless',
                    'settings' => [
                        'clients' => [
                            [
                                'id' => $vpnUser->uuid,
                                'level' => 0,
                                'email' => $vpnUser->xray_email,
                                'flow' => $vpnUser->flow,
                            ],
                        ],
                        'decryption' => 'none',
                    ],
                ],
            ],
        ];

        try {
            $json = json_encode(
                $payload,
                JSON_THROW_ON_ERROR |
                JSON_PRETTY_PRINT |
                JSON_UNESCAPED_SLASHES,
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Unable to prepare Xray user configuration.',
                previous: $exception,
            );
        }

        $temporaryPath = $this->createTemporaryJsonFile($json);

        try {
            $command = sprintf(
                '%s api adu --server=%s %s',
                escapeshellarg($this->binary()),
                escapeshellarg($this->apiServer()),
                escapeshellarg($temporaryPath),
            );

            $result = Process::timeout(5)->run($command);

            if ($result->failed()) {
                throw new RuntimeException(
                    'Unable to add VPN user to Xray.',
                );
            }
        } finally {
            @unlink($temporaryPath);
        }
    }

    public function removeUser(VpnUser $vpnUser): void
    {
        $command = sprintf(
            '%s api rmu --server=%s -tag=%s %s',
            escapeshellarg($this->binary()),
            escapeshellarg($this->apiServer()),
            escapeshellarg($this->inboundTag()),
            escapeshellarg($vpnUser->xray_email),
        );

        $result = Process::timeout(5)->run($command);

        if ($result->failed()) {
            throw new RuntimeException(
                'Unable to remove VPN user from Xray.',
            );
        }
    }

    /**
     * @return array{
     *     uplink_bytes: int,
     *     downlink_bytes: int,
     *     total_bytes: int
     * }
     */

    /**
     * @return array<int, string>
     */
    public function getInboundUserEmails(): array
    {
        $command = sprintf(
            '%s api inbounduser --server=%s -tag=%s',
            escapeshellarg($this->binary()),
            escapeshellarg($this->apiServer()),
            escapeshellarg($this->inboundTag()),
        );

        $result = Process::timeout(5)->run($command);

        if ($result->failed()) {
            throw new RuntimeException(
                'Unable to read VPN users from Xray.',
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
                'Xray returned invalid inbound user data.',
                previous: $exception,
            );
        }

        if (! is_array($payload)) {
            throw new RuntimeException(
                'Xray returned an invalid inbound user payload.',
            );
        }

        $users = $payload['users'] ?? [];

        if (! is_array($users)) {
            return [];
        }

        $emails = [];

        foreach ($users as $user) {
            if (! is_array($user)) {
                continue;
            }

            $email = $user['email'] ?? null;

            if (
                ! is_string($email) ||
                trim($email) === ''
            ) {
                continue;
            }

            $emails[] = $email;
        }

        return array_values(
            array_unique($emails),
        );
    }

    public function getOnlineSessionCount(
        VpnUser $vpnUser,
    ): int {
        $command = sprintf(
            '%s api statsonline --server=%s -email=%s',
            escapeshellarg($this->binary()),
            escapeshellarg($this->apiServer()),
            escapeshellarg($vpnUser->xray_email),
        );

        $result = Process::timeout(5)->run($command);

        if ($result->failed()) {
            $error = sprintf(
                '%s %s',
                $result->output(),
                $result->errorOutput(),
            );

            if (
                str_contains(
                    $error,
                    'code = NotFound',
                ) ||
                str_contains(
                    $error,
                    '>>>online not found',
                )
            ) {
                return 0;
            }

            throw new RuntimeException(
                'Unable to read VPN user online status from Xray.',
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
                'Xray returned invalid online status.',
                previous: $exception,
            );
        }

        if (! is_array($payload)) {
            return 0;
        }

        $stat = $payload['stat'] ?? null;

        if (! is_array($stat)) {
            return 0;
        }

        $value = $stat['value'] ?? 0;

        if (
            ! is_int($value) &&
            ! is_numeric($value)
        ) {
            return 0;
        }

        return max(
            0,
            (int) $value,
        );
    }

    public function getUserStats(VpnUser $vpnUser): array
    {
        $pattern = sprintf(
            'user>>>%s',
            $vpnUser->xray_email,
        );

        $command = sprintf(
            '%s api statsquery --server=%s -pattern %s',
            escapeshellarg($this->binary()),
            escapeshellarg($this->apiServer()),
            escapeshellarg($pattern),
        );

        $result = Process::timeout(5)->run($command);

        if ($result->failed()) {
            throw new RuntimeException(
                'Unable to read VPN user statistics from Xray.',
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
                'Xray returned invalid statistics.',
                previous: $exception,
            );
        }

        if (! is_array($payload)) {
            throw new RuntimeException(
                'Xray returned an invalid statistics payload.',
            );
        }

        $uplinkBytes = 0;
        $downlinkBytes = 0;

        $stats = $payload['stat'] ?? [];

        if (! is_array($stats)) {
            $stats = [];
        }

        foreach ($stats as $stat) {
            if (! is_array($stat)) {
                continue;
            }

            $name = $stat['name'] ?? null;
            $value = $stat['value'] ?? null;

            if (
                ! is_string($name) ||
                (! is_int($value) && ! is_numeric($value))
            ) {
                continue;
            }

            if (str_ends_with(
                $name,
                '>>>traffic>>>uplink',
            )) {
                $uplinkBytes = (int) $value;
            }

            if (str_ends_with(
                $name,
                '>>>traffic>>>downlink',
            )) {
                $downlinkBytes = (int) $value;
            }
        }

        return [
            'uplink_bytes' => $uplinkBytes,
            'downlink_bytes' => $downlinkBytes,
            'total_bytes' => $uplinkBytes + $downlinkBytes,
        ];
    }

    private function createTemporaryJsonFile(
        string $contents,
    ): string {
        $basePath = tempnam(
            sys_get_temp_dir(),
            'task-manager-xray-',
        );

        if ($basePath === false) {
            throw new RuntimeException(
                'Unable to create temporary Xray configuration.',
            );
        }

        $jsonPath = $basePath.'.json';

        if (! @rename($basePath, $jsonPath)) {
            @unlink($basePath);

            throw new RuntimeException(
                'Unable to prepare temporary Xray configuration.',
            );
        }

        if (file_put_contents($jsonPath, $contents) === false) {
            @unlink($jsonPath);

            throw new RuntimeException(
                'Unable to write temporary Xray configuration.',
            );
        }

        return $jsonPath;
    }

    private function binary(): string
    {
        return (string) config('xray.binary');
    }

    private function apiServer(): string
    {
        return (string) config('xray.api_server');
    }

    private function inboundTag(): string
    {
        return (string) config('xray.inbound_tag');
    }

    private function port(): int
    {
        return (int) config('xray.port');
    }
}
