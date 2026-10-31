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
     *     ws_host: string,
     *     ws_path: string,
     *     url: string
     * }
     */
    public function getConnectionData(
        VpnUser $vpnUser,
    ): array {
        $server = $this->requiredConfig('xray.server');

        $port = (int) config('xray.port', 443);

        $serverName = $this->requiredConfig(
            'xray.server_name',
        );

        $security = $this->requiredConfig(
            'xray.security',
        );

        $network = $this->requiredConfig(
            'xray.network',
        );

        $wsHost = $this->requiredConfig(
            'xray.ws_host',
        );

        $wsPath = $this->requiredConfig(
            'xray.ws_path',
        );

        if ($port < 1 || $port > 65535) {
            throw new RuntimeException(
                'Invalid VPN server port.',
            );
        }

        if ($security !== 'tls' || $network !== 'ws') {
            throw new RuntimeException(
                'VPN connection requires WebSocket over TLS.',
            );
        }

        if (!str_starts_with($wsPath, '/')) {
            throw new RuntimeException(
                'VPN WebSocket path must start with "/".',
            );
        }

        $query = http_build_query(
            [
                'encryption' => 'none',
                'security' => $security,
                'sni' => $serverName,
                'type' => $network,
                'host' => $wsHost,
                'path' => $wsPath,
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

            // WebSocket + TLS does not use Reality flow.
            'flow' => '',

            'security' => $security,
            'network' => $network,
            'server_name' => $serverName,

            // Legacy fields retained for API compatibility.
            'fingerprint' => '',
            'public_key' => '',
            'short_id' => '',

            'ws_host' => $wsHost,
            'ws_path' => $wsPath,

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
