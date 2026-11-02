<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\MonitoredServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class MonitoredServerRefreshTest extends TestCase
{
    use RefreshDatabase;

    private const URL =
        '/api/server-status/servers/germany/refresh';

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

    public function test_guest_cannot_refresh(): void
    {
        $this->createServer();

        $this->postJson(self::URL)
            ->assertUnauthorized();
    }

    public function test_inactive_user_cannot_refresh(): void
    {
        $this->createServer();
        $this->authenticate(false);

        $this->postJson(self::URL)
            ->assertForbidden();
    }

    public function test_unverified_user_cannot_refresh(): void
    {
        $this->createServer();

        Sanctum::actingAs(
            User::factory()->unverified()->create(),
        );

        $this->postJson(self::URL)
            ->assertForbidden();
    }

    public function test_refresh_updates_germany_only(): void
    {
        $this->authenticate();
        $this->createServer();

        Cache::put(
            'server-status:health:latest',
            ['cpu_percent' => 10],
        );

        Cache::put(
            'server-status:services:latest',
            ['services' => [['key' => 'iran-only']]],
        );

        $this->fakeSuccess();

        $this->postJson(self::URL)
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('server.slug', 'germany')
            ->assertJsonPath('health.cpu_percent', 42.5)
            ->assertJsonPath(
                'services.services.0.status',
                'active',
            );

        $this->assertEquals(
            42.5,
            Cache::get(self::HEALTH_KEY)['cpu_percent'],
        );

        $this->assertSame(
            'active',
            Cache::get(self::SERVICES_KEY)
            ['services'][0]['status'],
        );

        // Iran must remain unchanged.
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

    public function test_local_server_cannot_use_remote_refresh(): void
    {
        $this->authenticate();
        $this->createServer('iran', 'local');

        $this->postJson(
            '/api/server-status/servers/iran/refresh',
        )->assertNotFound();
    }

    public function test_disabled_server_returns_404(): void
    {
        $this->authenticate();
        $this->createServer('germany', 'ssh', false);

        $this->postJson(self::URL)
            ->assertNotFound();
    }

    public function test_disabled_monitoring_returns_503(): void
    {
        $this->authenticate();
        $this->createServer();

        config()->set(
            'server_monitoring.remotes.germany.enabled',
            false,
        );

        $this->postJson(self::URL)
            ->assertStatus(503);
    }

    public function test_ssh_failure_returns_503(): void
    {
        $this->authenticate();
        $this->createServer();

        Process::fake([
            '*' => Process::result(
                output: '',
                errorOutput: 'Connection failed',
                exitCode: 255,
            ),
        ]);

        $this->postJson(self::URL)
            ->assertStatus(503)
            ->assertJsonPath('status', 'error');

        $this->assertNull(Cache::get(self::HEALTH_KEY));
        $this->assertNull(Cache::get(self::SERVICES_KEY));
    }

    public function test_concurrent_refresh_returns_409(): void
    {
        $this->authenticate();
        $this->createServer();

        $lock = Cache::lock(
            'server-status:servers:germany:refresh',
            30,
        );

        $this->assertTrue($lock->get());

        try {
            $this->postJson(self::URL)
                ->assertStatus(409)
                ->assertJsonPath('status', 'busy');
        } finally {
            $lock->release();
        }
    }

    private function authenticate(
        bool $active = true,
    ): void {
        Sanctum::actingAs(
            User::factory()->create([
                'is_active' => $active,
            ]),
        );
    }

    private function createServer(
        string $slug = 'germany',
        string $driver = 'ssh',
        bool $enabled = true,
    ): void {
        MonitoredServer::query()->create([
            'slug' => $slug,
            'name' => ucfirst($slug) . ' Server',
            'region' => ucfirst($slug),
            'driver' => $driver,
            'is_enabled' => $enabled,
            'sort_order' => 1,
        ]);
    }

    private function fakeSuccess(): void
    {
        $now = now()->utc()->toIso8601String();

        Process::fake([
            '*' => Process::result(
                output: json_encode([
                    'schema_version' => 1,
                    'health' => [
                        'cpu_percent' => 42.5,
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
                ], JSON_THROW_ON_ERROR),
            ),
        ]);
    }
}
