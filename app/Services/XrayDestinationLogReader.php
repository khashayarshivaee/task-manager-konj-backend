<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;

class XrayDestinationLogReader
{
    /**
     * @return array{
     *     occurred_at: CarbonImmutable,
     *     client_ip: string,
     *     client_port: int,
     *     protocol: string,
     *     destination: string,
     *     destination_port: int,
     *     xray_email: string
     * }|null
     */
    public function parseLine(
        string $line,
    ): ?array {
        $line = trim($line);

        if ($line === '') {
            return null;
        }

        $pattern = '/^'
            . '(?<timestamp>\d{4}\/\d{2}\/\d{2}\s+\d{2}:\d{2}:\d{2}\.\d+)'
            . '\s+from\s+'
            . '(?<client_ip>[^\s:]+):(?<client_port>\d+)'
            . '\s+accepted\s+'
            . '(?<protocol>[a-zA-Z0-9_-]+):'
            . '(?<destination>.+):(?<destination_port>\d+)'
            . '\s+\[[^\]]+\]'
            . '\s+email:\s+'
            . '(?<xray_email>\S+)'
            . '$/';

        if (
            preg_match(
                $pattern,
                $line,
                $matches,
            ) !== 1
        ) {
            return null;
        }

        return [
            'occurred_at' =>
                CarbonImmutable::createFromFormat(
                    'Y/m/d H:i:s.u',
                    $matches['timestamp'],
                    'UTC',
                ),

            'client_ip' =>
                $matches['client_ip'],

            'client_port' =>
                (int) $matches['client_port'],

            'protocol' =>
                strtolower(
                    $matches['protocol'],
                ),

            'destination' =>
                strtolower(
                    $matches['destination'],
                ),

            'destination_port' =>
                (int) $matches['destination_port'],

            'xray_email' =>
                $matches['xray_email'],
        ];
    }
}
