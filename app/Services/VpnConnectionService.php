<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\VpnUser;
use RuntimeException;

class VpnConnectionService
{
    /**
     * @return array{
     *     server: string,
     *     port: int,
     *     uuid: string,
     *     flow: string,
     *     security: string,
     *     network: string,
     *     server_name: string,
     *     fingerprint: string,
     *     public_key: string,
     *     short_id: string,
     *     url: string
     * }
     */
    public function getConnectionData(
        VpnUser $vpnUser,
    ): array {
        $server = $this->requiredConfig('xray.server');
        $serverName = $this->requiredConfig(
            'xray.server_name',
        );
        $publicKey = $this->requiredConfig(
            'xray.public_key',
        );
        $shortId = $this->requiredConfig(
            'xray.short_id',
        );
        $fingerprint = $this->requiredConfig(
            'xray.fingerprint',
        );

        $port = (int) config('xray.port', 443);

        $query = http_build_query(
            [
                'encryption' => 'none',
                'flow' => $vpnUser->flow,
                'security' => 'reality',
                'sni' => $serverName,
                'fp' => $fingerprint,
                'pbk' => $publicKey,
                'sid' => $shortId,
                'type' => 'tcp',
            ],
            '',
            '&',
            PHP_QUERY_RFC3986,
        );

        $url = sprintf(
            'vless://%s@%s:%d?%s#%s',
            $vpnUser->uuid,
            $server,
            $port,
            $query,
            rawurlencode($vpnUser->name),
        );

        return [
            'server' => $server,
            'port' => $port,
            'uuid' => $vpnUser->uuid,
            'flow' => $vpnUser->flow,
            'security' => 'reality',
            'network' => 'tcp',
            'server_name' => $serverName,
            'fingerprint' => $fingerprint,
            'public_key' => $publicKey,
            'short_id' => $shortId,
            'url' => $url,
        ];
    }

    private function requiredConfig(
        string $key,
    ): string {
        $value = trim(
            (string) config($key),
        );

        if ($value === '') {
            throw new RuntimeException(
                sprintf(
                    'Missing required VPN configuration: %s',
                    $key,
                ),
            );
        }

        return $value;
    }
}
