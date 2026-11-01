<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\VpnUser;
use App\Services\VpnConnectionService;
use RuntimeException;
use Tests\TestCase;

class VpnConnectionServiceTest extends TestCase
{
    public function test_it_builds_vless_websocket_tls_connection_data(): void
    {
        config([
            'xray.server' => 'edge.maranonline.com',
            'xray.port' => 443,
            'xray.server_name' => 'edge.maranonline.com',
            'xray.security' => 'tls',
            'xray.network' => 'ws',
            'xray.ws_host' => 'edge.maranonline.com',
            'xray.ws_path' => '/edge-stream',
        ]);

        $vpnUser = new VpnUser([
            'name' => 'Khashayar iPhone',
            'uuid' => '6cf6a408-8edb-4f1e-b902-235f58211e1e',
            'flow' => 'xtls-rprx-vision',
        ]);

        $service = app(VpnConnectionService::class);

        $connection = $service->getConnectionData(
            $vpnUser,
        );

        $this->assertSame(
            'edge.maranonline.com',
            $connection['server'],
        );

        $this->assertSame(
            443,
            $connection['port'],
        );

        $this->assertSame(
            '6cf6a408-8edb-4f1e-b902-235f58211e1e',
            $connection['uuid'],
        );

        // WS + TLS must not use Reality flow.
        $this->assertSame(
            '',
            $connection['flow'],
        );

        $this->assertSame(
            'tls',
            $connection['security'],
        );

        $this->assertSame(
            'ws',
            $connection['network'],
        );

        $this->assertSame(
            'edge.maranonline.com',
            $connection['server_name'],
        );

        $this->assertSame(
            'edge.maranonline.com',
            $connection['ws_host'],
        );

        $this->assertSame(
            '/edge-stream',
            $connection['ws_path'],
        );

        // Legacy Reality fields must be empty.
        $this->assertSame(
            '',
            $connection['fingerprint'],
        );

        $this->assertSame(
            '',
            $connection['public_key'],
        );

        $this->assertSame(
            '',
            $connection['short_id'],
        );

        $this->assertSame(
            'vless://6cf6a408-8edb-4f1e-b902-235f58211e1e'
            .'@edge.maranonline.com:443'
            .'?encryption=none'
            .'&security=tls'
            .'&sni=edge.maranonline.com'
            .'&type=ws'
            .'&host=edge.maranonline.com'
            .'&path=%2Fedge-stream'
            .'#Khashayar%20iPhone',
            $connection['url'],
        );
    }

    public function test_it_fails_when_required_xray_config_is_missing(): void
    {
        config([
            'xray.server' => '',
            'xray.port' => 443,
            'xray.server_name' => 'edge.maranonline.com',
            'xray.security' => 'tls',
            'xray.network' => 'ws',
            'xray.ws_host' => 'edge.maranonline.com',
            'xray.ws_path' => '/edge-stream',
        ]);

        $vpnUser = new VpnUser([
            'name' => 'Test Device',
            'uuid' => '6cf6a408-8edb-4f1e-b902-235f58211e1e',
            'flow' => '',
        ]);

        $this->expectException(
            RuntimeException::class,
        );

        app(VpnConnectionService::class)
            ->getConnectionData($vpnUser);
    }
}
