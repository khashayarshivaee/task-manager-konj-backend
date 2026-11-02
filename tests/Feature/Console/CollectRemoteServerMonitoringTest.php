<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\MonitoredServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

final class CollectRemoteServerMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private const COMMAND = 'server-status:collect-remote';

    private const HEALTH_KEY =
        'server-status:servers:germany:health:latest';

    private const SERVICES_KEY =
        'server-status:servers:germany:services:latest';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

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

    public function test_valid_snapshot_is_cached(): void
    {
        $this->createServer();

        Cache::put(
            'server-status:health:latest',
            ['cpu_percent' => 10],
        );

        Cache::put(
            'server-status:services:latest',
            ['services' => [['key' => 'iran-only']]],
        );

        $this->fakeSnapshot($this->payload());

        $this->assertSame(0, $this->collect());

        $this->assertSame(
            25.5,
            Cache::get(self::HEALTH_KEY)['cpu_percent'],
        );

        $this->assertSame(
            'active',
            Cache::get(self::SERVICES_KEY)
            ['services'][0]['status'],
        );

        // Existing Iran caches must remain unchanged.
        $this->assertSame(
            10,
            Cache::get('server-status:health:latest')
            ['cpu_percent'],
        );

        $this->assertSame(
            'iran-only',
            Cache::get('server-status:services:latest')
            ['services'][0]['key'],
        );
    }

    public function test_disabled_monitoring_fails(): void
    {
        $this->createServer();

        config()->set(
            'server_monitoring.remotes.germany.enabled',
            false,
        );

        Process::fake();

        $this->assertSame(1, $this->collect());

        $this->assertNull(Cache::get(self::HEALTH_KEY));
        $this->assertNull(Cache::get(self::SERVICES_KEY));
    }

    public function test_ssh_failure_does_not_cache_data(): void
    {
        $this->createServer();

        Process::fake([
            '*' => Process::result(
                output: '',
                errorOutput: 'Connection refused',
                exitCode: 255,
            ),
        ]);

        $this->assertSame(1, $this->collect());

        $this->assertNull(Cache::get(self::HEALTH_KEY));
        $this->assertNull(Cache::get(self::SERVICES_KEY));
    }

    public function test_invalid_cpu_is_rejected(): void
    {
        $this->createServer();

        $payload = $this->payload();

        $payload['health']['cpu_percent'] = 150;

        $this->fakeSnapshot($payload);

        $this->assertSame(1, $this->collect());

        $this->assertNull(Cache::get(self::HEALTH_KEY));
        $this->assertNull(Cache::get(self::SERVICES_KEY));
    }

    public function test_expired_snapshot_is_rejected(): void
    {
        $this->createServer();

        $payload = $this->payload();

        $old = now()
            ->subMinutes(10)
            ->utc()
            ->toIso8601String();

        $payload['health']['measured_at'] = $old;
        $payload['services']['checked_at'] = $old;

        $this->fakeSnapshot($payload);

        $this->assertSame(1, $this->collect());

        $this->assertNull(Cache::get(self::HEALTH_KEY));
        $this->assertNull(Cache::get(self::SERVICES_KEY));
    }

    public function test_unknown_server_is_rejected(): void
    {
        Process::fake();

        $this->assertSame(1, $this->collect());

        $this->assertNull(Cache::get(self::HEALTH_KEY));
        $this->assertNull(Cache::get(self::SERVICES_KEY));
    }

    private function createServer(): void
    {
        MonitoredServer::query()->create([
            'slug' => 'germany',
            'name' => 'Germany Server',
            'region' => 'Germany',
            'driver' => 'ssh',
            'is_enabled' => true,
            'sort_order' => 1,
        ]);
    }

    private function collect(): int
    {
        return Artisan::call(self::COMMAND, [
            'server' => 'germany',
        ]);
    }

    private function fakeSnapshot(array $payload): void
    {
        Process::fake([
            '*' => Process::result(
                output: json_encode(
                    $payload,
                    JSON_THROW_ON_ERROR,
                ),
            ),
        ]);
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
