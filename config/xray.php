<?php

declare(strict_types=1);

return [
    'binary' => env(
        'XRAY_BINARY',
        '/usr/local/bin/xray',
    ),

    'service_name' => env(
        'XRAY_SERVICE_NAME',
        'xray.service',
    ),

    'api_server' => env(
        'XRAY_API_SERVER',
        '127.0.0.1:11086',
    ),

    'inbound_tag' => env(
        'XRAY_INBOUND_TAG',
        'vless-ws-edge',
    ),

    'resync_enabled' => env(
        'XRAY_RESYNC_ENABLED',
        false,
    ),

    /*
 * Remote VPN destinations collector.
 * Disabled until production verification.
 */
    'remote_destinations_enabled' => env(
        'XRAY_REMOTE_DESTINATIONS_ENABLED',
        false,
    ),

    'server' => env(
        'XRAY_SERVER',
        'edge.maranonline.com',
    ),

    'port' => (int) env(
        'XRAY_PORT',
        443,
    ),

    'inbound_port' => (int) env(
        'XRAY_INBOUND_PORT',
        12087,
    ),

    'network' => env(
        'XRAY_NETWORK',
        'ws',
    ),

    'security' => env(
        'XRAY_SECURITY',
        'tls',
    ),

    'server_name' => env(
        'XRAY_SERVER_NAME',
        'edge.maranonline.com',
    ),

    'ws_host' => env(
        'XRAY_WS_HOST',
        'edge.maranonline.com',
    ),

    'ws_path' => env(
        'XRAY_WS_PATH',
        '/edge-stream',
    ),

    // Legacy Reality settings.
    // Kept temporarily for compatibility with existing services.

    'public_key' => env(
        'XRAY_REALITY_PUBLIC_KEY',
    ),

    'short_id' => env(
        'XRAY_REALITY_SHORT_ID',
    ),

    'fingerprint' => env(
        'XRAY_FINGERPRINT',
        'chrome',
    ),

    'flow' => env(
        'XRAY_FLOW',
        '',
    ),

    'access_log' => env(
        'XRAY_ACCESS_LOG',
        '/var/log/xray/access.log',
    ),
];
