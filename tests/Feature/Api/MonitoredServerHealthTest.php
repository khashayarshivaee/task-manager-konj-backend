<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\MonitoredServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class MonitoredServerHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_guest_cannot_read_health(): void
    {
        $this->createServer('iran', 'local');

        $this->getJson(
            '/api/server-status/servers/iran/health'
        )->assertUnauthorized();
    }

    public function test_iran_uses_existing_health_cache(): void
    {
        $this->authenticate();

        $this->createServer('iran', 'local');

        Cache::put(
            'server-status:health:latest',
            $this->snapshot(25.5),
            now()->addMinutes(3),
        );

        $this->getJson(
            '/api/server-status/servers/iran/health'
        )
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('server.slug', 'iran')
            ->assertJsonPath('data.cpu_percent', 25.5);
    }

    public function test_germany_uses_its_own_cache(): void
    {
        $this->authenticate();

        $this->createServer('iran', 'local');
        $this->createServer('germany', 'ssh');

        Cache::put(
            'server-status:health:latest',
            $this->snapshot(15.0),
            now()->addMinutes(3),
        );

        Cache::put(
            'server-status:servers:germany:health:latest',
            $this->snapshot(85.0),
            now()->addMinutes(3),
        );

        $this->getJson(
            '/api/server-status/servers/iran/health'
        )
            ->assertOk()
            ->assertJsonPath('data.cpu_percent', 15);

        $this->getJson(
            '/api/server-status/servers/germany/health'
        )
            ->assertOk()
            ->assertJsonPath('server.slug', 'germany')
            ->assertJsonPath('data.cpu_percent', 85);
    }

    public function test_germany_does_not_fallback_to_iran(): void
    {
        $this->authenticate();

        $this->createServer('germany', 'ssh');

        Cache::put(
            'server-status:health:latest',
            $this->snapshot(45.0),
            now()->addMinutes(3),
        );

        $this->getJson(
            '/api/server-status/servers/germany/health'
        )
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('server.slug', 'germany')
            ->assertJsonPath('data', null);
    }

    public function test_disabled_server_returns_404(): void
    {
        $this->authenticate();

        $this->createServer('germany', 'ssh', false);

        $this->getJson(
            '/api/server-status/servers/germany/health'
        )->assertNotFound();
    }

    public function test_unknown_server_returns_404(): void
    {
        $this->authenticate();

        $this->getJson(
            '/api/server-status/servers/unknown/health'
        )->assertNotFound();
    }

    public function test_inactive_user_cannot_read_health(): void
    {
        $this->createServer('iran', 'local');

        $this->authenticate(false);

        $this->getJson(
            '/api/server-status/servers/iran/health'
        )->assertForbidden();
    }

    public function test_unverified_user_cannot_read_health(): void
    {
        $this->createServer('iran', 'local');

        Sanctum::actingAs(
            User::factory()->unverified()->create()
        );

        $this->getJson(
            '/api/server-status/servers/iran/health'
        )->assertForbidden();
    }



    public function test_expired_health_snapshot_returns_pending(): void
    {
        $this->authenticate();

        $this->createServer('germany', 'ssh');

        $snapshot = $this->snapshot(35.0);

        $snapshot['measured_at'] = now()
            ->subMinutes(5)
            ->utc()
            ->toIso8601String();

        Cache::put(
            'server-status:servers:germany:health:latest',
            $snapshot,
            now()->addMinutes(3),
        );

        $this->getJson(
            '/api/server-status/servers/germany/health'
        )
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('data', null);
    }

    public function test_invalid_health_timestamp_returns_pending(): void
    {
        $this->authenticate();

        $this->createServer('germany', 'ssh');

        $snapshot = $this->snapshot(40.0);

        $snapshot['measured_at'] = 'invalid-date';

        Cache::put(
            'server-status:servers:germany:health:latest',
            $snapshot,
            now()->addMinutes(3),
        );

        $this->getJson(
            '/api/server-status/servers/germany/health'
        )
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('data', null);
    }

    public function test_future_health_timestamp_returns_pending(): void
    {
        $this->authenticate();

        $this->createServer('germany', 'ssh');

        $snapshot = $this->snapshot(50.0);

        $snapshot['measured_at'] = now()
            ->addMinutes(5)
            ->utc()
            ->toIso8601String();

        Cache::put(
            'server-status:servers:germany:health:latest',
            $snapshot,
            now()->addMinutes(3),
        );

        $this->getJson(
            '/api/server-status/servers/germany/health'
        )
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('data', null);
    }


    private function authenticate(
        bool $active = true
    ): void {
        Sanctum::actingAs(
            User::factory()->create([
                'is_active' => $active,
            ])
        );
    }

    private function createServer(
        string $slug,
        string $driver,
        bool $enabled = true,
    ): MonitoredServer {
        return MonitoredServer::query()->create([
            'slug' => $slug,
            'name' => ucfirst($slug) . ' Server',
            'region' => ucfirst($slug),
            'driver' => $driver,
            'is_enabled' => $enabled,
            'sort_order' => 0,
        ]);
    }

    private function snapshot(float $cpu): array
    {
        return [
            'cpu_percent' => $cpu,
            'memory_total_bytes' => 8000000000,
            'memory_used_bytes' => 4000000000,
            'memory_percent' => 50.0,
            'disk_total_bytes' => 100000000000,
            'disk_used_bytes' => 40000000000,
            'disk_percent' => 40.0,
            'uptime_seconds' => 86400,
            'measured_at' => now()
                ->utc()
                ->toIso8601String(),
        ];
    }
}
