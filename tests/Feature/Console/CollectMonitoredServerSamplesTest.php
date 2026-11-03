<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\MonitoredServer;
use App\Models\MonitoredServerSample;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

final class CollectMonitoredServerSamplesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['germany', 'company'] as $slug) {
            config()->set(
                "server_monitoring.remotes.{$slug}",
                [
                    'enabled' => true,
                    'host' => "{$slug}.monitor.test",
                    'port' => 22,
                    'user' => 'konjmonitor',
                    'identity_file' => '/tmp/monitoring-test-key',
                    'known_hosts_file' => '/tmp/monitoring-test-hosts',
                    'remote_command' => 'snapshot',
                    'connect_timeout' => 8,
                    'process_timeout' => 20,
                ],
            );
        }
    }

    public function test_records_two_remote_servers_independently(): void
    {
        $this->createServer('germany');
        $this->createServer('company');

        Process::fake([
            '*' => Process::result(
                output: $this->snapshot(42.5),
            ),
        ]);

        $this->assertSame(0, $this->collect());

        $this->assertDatabaseCount(
            'monitored_server_samples',
            2,
        );

        $this->assertSame(
            2,
            MonitoredServerSample::query()
                ->distinct()
                ->count('monitored_server_id'),
        );
    }

    public function test_one_server_failure_does_not_stop_others(): void
    {
        $this->createServer('germany');
        $company = $this->createServer('company');

        Process::fake([
            '*germany.monitor.test*' => Process::result(
                output: '',
                errorOutput: 'Connection refused',
                exitCode: 255,
            ),
            '*company.monitor.test*' => Process::result(
                output: $this->snapshot(35.0),
            ),
        ]);

        $this->assertSame(1, $this->collect());

        $this->assertDatabaseCount(
            'monitored_server_samples',
            1,
        );

        $this->assertDatabaseHas(
            'monitored_server_samples',
            [
                'monitored_server_id' => $company->id,
                'cpu_percent' => 35.0,
            ],
        );
    }

    public function test_disabled_remote_is_skipped(): void
    {
        $this->createServer('germany');

        config()->set(
            'server_monitoring.remotes.germany.enabled',
            false,
        );

        Process::fake();

        $this->assertSame(0, $this->collect());

        $this->assertDatabaseCount(
            'monitored_server_samples',
            0,
        );
    }

    public function test_invalid_measurements_are_rejected(): void
    {
        $this->createServer('germany');

        Process::fake([
            '*' => Process::result(
                output: $this->snapshot(150.0),
            ),
        ]);

        $this->assertSame(1, $this->collect());

        $this->assertDatabaseCount(
            'monitored_server_samples',
            0,
        );
    }

    public function test_same_measurement_is_not_duplicated(): void
    {
        $this->createServer('germany');

        $snapshot = $this->snapshot(25.0);

        Process::fake([
            '*' => Process::result(
                output: $snapshot,
            ),
        ]);

        $this->assertSame(0, $this->collect());
        $this->assertSame(0, $this->collect());

        $this->assertDatabaseCount(
            'monitored_server_samples',
            1,
        );
    }

    private function createServer(
        string $slug,
    ): MonitoredServer {
        return MonitoredServer::query()->create([
            'slug' => $slug,
            'name' => ucfirst($slug) . ' Server',
            'region' => null,
            'driver' => 'ssh',
            'is_enabled' => true,
            'sort_order' => 1,
        ]);
    }

    private function collect(): int
    {
        return Artisan::call(
            'server-status:collect-samples',
        );
    }

    private function snapshot(
        float $cpu,
    ): string {
        $now = now()->utc()->toIso8601String();

        return json_encode([
            'schema_version' => 1,

            'health' => [
                'cpu_percent' => $cpu,
                'memory_percent' => 40.0,
                'memory_total_bytes' => 8000000000,
                'memory_used_bytes' => 3200000000,
                'disk_percent' => 30.0,
                'disk_total_bytes' => 100000000000,
                'disk_used_bytes' => 30000000000,
                'uptime_seconds' => 86400,
                'measured_at' => $now,
            ],

            'services' => [
                'services' => [],
                'checked_at' => $now,
            ],
        ], JSON_THROW_ON_ERROR);
    }
}
