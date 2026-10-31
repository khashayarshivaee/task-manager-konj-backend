<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Services\XrayRuntimeService;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class XrayRuntimeServiceTest extends TestCase
{
    public function test_it_reads_xray_uptime_from_management_api(): void
    {
        config([
            'xray.binary' => '/usr/local/bin/xray',
            'xray.api_server' => '127.0.0.1:11086',
        ]);

        Process::fake([
            '*' => Process::result(
                '{"Uptime":529235}',
            ),
        ]);

        $service = app(XrayRuntimeService::class);

        $this->assertSame(
            529235,
            $service->getUptimeSeconds(),
        );
    }


    public function test_it_fails_when_xray_api_is_unavailable(): void
    {
        config([
            'xray.binary' => '/usr/local/bin/xray',
            'xray.api_server' => '127.0.0.1:11086',
        ]);

        Process::fake([
            '*' => Process::result(
                '',
                'Connection refused',
                1,
            ),
        ]);

        $service = app(XrayRuntimeService::class);

        $this->expectException(
            \RuntimeException::class,
        );

        $this->expectExceptionMessage(
            'Unable to read Xray runtime information.',
        );

        $service->getUptimeSeconds();
    }


    public function test_it_rejects_invalid_xray_uptime(): void
    {
        config([
            'xray.binary' => '/usr/local/bin/xray',
            'xray.api_server' => '127.0.0.1:11086',
        ]);

        Process::fake([
            '*' => Process::result(
                '{"Uptime":"invalid"}',
            ),
        ]);

        $service = app(XrayRuntimeService::class);

        $this->expectException(
            \RuntimeException::class,
        );

        $this->expectExceptionMessage(
            'Xray returned an invalid uptime.',
        );

        $service->getUptimeSeconds();
    }


    public function test_it_detects_xray_restart_using_uptime(): void
    {
        $service = app(XrayRuntimeService::class);

        // Normal operation.
        $this->assertFalse(
            $service->hasRestarted(1000, 1060, 60),
        );

        // Restart shortly after previous sample.
        $this->assertTrue(
            $service->hasRestarted(1000, 20, 60),
        );

        // Restart during a longer collection gap.
        $this->assertTrue(
            $service->hasRestarted(1000, 1100, 300),
        );

        // Small sampling-time differences are tolerated.
        $this->assertFalse(
            $service->hasRestarted(1000, 1055, 60),
        );
    }

}
