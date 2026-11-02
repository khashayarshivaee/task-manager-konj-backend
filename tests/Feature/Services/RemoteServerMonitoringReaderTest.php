<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Services\RemoteServerMonitoringReaderService;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Tests\TestCase;

final class RemoteServerMonitoringReaderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('server_monitoring.remotes.germany', [
            'enabled' => true,
            'host' => 'monitor.example.test',
            'port' => 22,
            'user' => 'deploy',
            'identity_file' => '/tmp/monitoring-test-key',
            'known_hosts_file' => '/tmp/monitoring-test-hosts',
            'remote_command' => 'snapshot',
            'connect_timeout' => 8,
            'process_timeout' => 20,
        ]);
    }

    public function test_valid_snapshot_is_read_successfully(): void
    {
        Process::fake([
            '*' => Process::result(
                output: $this->validPayload(),
            ),
        ]);

        $result = $this->reader()->read('germany');

        $this->assertSame(
            25.5,
            $result['health']['cpu_percent'],
        );

        $this->assertSame(
            'active',
            $result['services']['services'][0]['status'],
        );
    }

    public function test_disabled_monitoring_is_rejected(): void
    {
        config()->set(
            'server_monitoring.remotes.germany.enabled',
            false,
        );

        Process::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Remote monitoring is disabled.',
        );

        $this->reader()->read('germany');
    }

    public function test_unknown_server_is_rejected(): void
    {
        Process::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Monitoring server is not configured.',
        );

        $this->reader()->read('unknown');
    }

    public function test_invalid_slug_is_rejected(): void
    {
        Process::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Invalid monitoring server.',
        );

        $this->reader()->read('../germany');
    }

    public function test_ssh_failure_is_handled(): void
    {
        Process::fake([
            '*' => Process::result(
                output: '',
                errorOutput: 'Connection refused',
                exitCode: 255,
            ),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Unable to collect remote server monitoring data.',
        );

        $this->reader()->read('germany');
    }

    public function test_invalid_json_is_rejected(): void
    {
        Process::fake([
            '*' => Process::result(
                output: '{invalid-json',
            ),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Invalid remote monitoring JSON.',
        );

        $this->reader()->read('germany');
    }

    public function test_invalid_schema_version_is_rejected(): void
    {
        $payload = $this->payload();

        $payload['schema_version'] = 2;

        Process::fake([
            '*' => Process::result(
                output: json_encode(
                    $payload,
                    JSON_THROW_ON_ERROR,
                ),
            ),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Invalid remote monitoring payload.',
        );

        $this->reader()->read('germany');
    }

    public function test_missing_services_are_rejected(): void
    {
        $payload = $this->payload();

        unset($payload['services']);

        Process::fake([
            '*' => Process::result(
                output: json_encode(
                    $payload,
                    JSON_THROW_ON_ERROR,
                ),
            ),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Invalid remote monitoring payload.',
        );

        $this->reader()->read('germany');
    }

    public function test_oversized_response_is_rejected(): void
    {
        Process::fake([
            '*' => Process::result(
                output: str_repeat('X', 65537),
            ),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Invalid remote monitoring response size.',
        );

        $this->reader()->read('germany');
    }

    public function test_arbitrary_remote_command_is_rejected(): void
    {
        config()->set(
            'server_monitoring.remotes.germany.remote_command',
            'cat /etc/passwd',
        );

        Process::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Invalid remote monitoring configuration.',
        );

        $this->reader()->read('germany');
    }

    private function reader(): RemoteServerMonitoringReaderService
    {
        return app(
            RemoteServerMonitoringReaderService::class,
        );
    }

    private function validPayload(): string
    {
        return json_encode(
            $this->payload(),
            JSON_THROW_ON_ERROR,
        );
    }

    private function payload(): array
    {
        $now = now()->utc()->toIso8601String();

        return [
            'schema_version' => 1,

            'health' => [
                'cpu_percent' => 25.5,
                'memory_total_bytes' => 8000000000,
                'memory_used_bytes' => 4000000000,
                'memory_percent' => 50.0,
                'disk_total_bytes' => 100000000000,
                'disk_used_bytes' => 40000000000,
                'disk_percent' => 40.0,
                'uptime_seconds' => 86400,
                'measured_at' => $now,
            ],

            'services' => [
                'services' => [
                    [
                        'key' => 'nginx',
                        'name' => 'Nginx',
                        'status' => 'active',
                        'uptime_seconds' => 3600,
                    ],
                ],
                'checked_at' => $now,
            ],
        ];
    }
}
