<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\VpnUser;
use App\Services\VpnConnectionService;
use Tests\TestCase;

class VpnConnectionServiceTest extends TestCase
{
    public function test_it_builds_vless_reality_connection_data(): void
    {
        config([
            'xray.server' => '91.98.105.190',
            'xray.port' => 443,
            'xray.server_name' => 'www.hetzner.com',
            'xray.public_key' => 'test-public-key',
            'xray.short_id' => '9d2929a247ef305e',
            'xray.fingerprint' => 'chrome',
        ]);

        $vpnUser = new VpnUser([
            'name' => 'Khashayar iPhone',
            'uuid' => '6cf6a408-8edb-4f1e-b902-235f58211e1e',
            'flow' => 'xtls-rprx-vision',
        ]);

        $service = app(
            VpnConnectionService::class,
        );

        $connection = $service->getConnectionData(
            $vpnUser,
        );

        $this->assertSame(
            '91.98.105.190',
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

        $this->assertSame(
            'xtls-rprx-vision',
            $connection['flow'],
        );

        $this->assertSame(
            'reality',
            $connection['security'],
        );

        $this->assertSame(
            'tcp',
            $connection['network'],
        );

        $this->assertSame(
            'www.hetzner.com',
            $connection['server_name'],
        );

        $this->assertSame(
            'chrome',
            $connection['fingerprint'],
        );

        $this->assertSame(
            'test-public-key',
            $connection['public_key'],
        );

        $this->assertSame(
            '9d2929a247ef305e',
            $connection['short_id'],
        );

        $this->assertSame(
            'vless://6cf6a408-8edb-4f1e-b902-235f58211e1e'
            .'@91.98.105.190:443'
            .'?encryption=none'
            .'&flow=xtls-rprx-vision'
            .'&security=reality'
            .'&sni=www.hetzner.com'
            .'&fp=chrome'
            .'&pbk=test-public-key'
            .'&sid=9d2929a247ef305e'
            .'&type=tcp'
            .'#Khashayar%20iPhone',
            $connection['url'],
        );
    }

    public function test_it_fails_when_required_xray_config_is_missing(): void
    {
        config([
            'xray.server' => '',
            'xray.server_name' => 'www.hetzner.com',
            'xray.public_key' => 'test-public-key',
            'xray.short_id' => '9d2929a247ef305e',
            'xray.fingerprint' => 'chrome',
        ]);

        $vpnUser = new VpnUser([
            'name' => 'Test Device',
            'uuid' => '6cf6a408-8edb-4f1e-b902-235f58211e1e',
            'flow' => 'xtls-rprx-vision',
        ]);

        $this->expectException(
            \RuntimeException::class,
        );

        app(VpnConnectionService::class)
            ->getConnectionData($vpnUser);
    }
}
