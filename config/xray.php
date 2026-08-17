<?php

declare(strict_types=1);

return [
    'binary' => env(
        'XRAY_BINARY',
        '/usr/local/bin/xray',
    ),

    'api_server' => env(
        'XRAY_API_SERVER',
        '127.0.0.1:10085',
    ),

    'inbound_tag' => env(
        'XRAY_INBOUND_TAG',
        'vless-reality',
    ),

    'server' => env(
        'XRAY_SERVER',
        '91.98.105.190',
    ),

    'port' => (int) env(
        'XRAY_PORT',
        443,
    ),

    'server_name' => env(
        'XRAY_SERVER_NAME',
        'www.hetzner.com',
    ),

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
        'xtls-rprx-vision',
    ),
];
