<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\MonitoredServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class MonitoredServerServicesStatusTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT =
        '/api/server-status/servers/';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_guest_cannot_read_services(): void
    {
        $this->createServer('iran', 'local');

        $this->getJson(
            self::ENDPOINT . 'iran/services'
        )->assertUnauthorized();
    }

    public function test_iran_uses_existing_services_cache(): void
    {
        $this->authenticate();

        $this->createServer('iran', 'local');

        Cache::put(
            'server-status:services:latest',
            $this->snapshot('active'),
            now()->addMinutes(3),
        );

        $this->getJson(
            self::ENDPOINT . 'iran/services'
        )
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('server.slug', 'iran')
            ->assertJsonPath(
                'data.services.0.status',
                'active',
            )
            ->assertJsonPath(
                'data.services.0.key',
                'nginx',
            );
    }

    public function test_germany_uses_independent_cache(): void
    {
        $this->authenticate();

        $this->createServer('iran', 'local');
        $this->createServer('germany', 'ssh');

        Cache::put(
            'server-status:services:latest',
            $this->snapshot('active'),
            now()->addMinutes(3),
        );

        Cache::put(
            'server-status:servers:germany:services:latest',
            $this->snapshot('down'),
            now()->addMinutes(3),
        );

        $this->getJson(
            self::ENDPOINT . 'iran/services'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.services.0.status',
                'active',
            );

        $this->getJson(
            self::ENDPOINT . 'germany/services'
        )
            ->assertOk()
            ->assertJsonPath('server.slug', 'germany')
            ->assertJsonPath(
                'data.services.0.status',
                'down',
            );
    }

    public function test_germany_never_falls_back_to_iran(): void
    {
        $this->authenticate();

        $this->createServer('germany', 'ssh');

        Cache::put(
            'server-status:services:latest',
            $this->snapshot('active'),
            now()->addMinutes(3),
        );

        $this->getJson(
            self::ENDPOINT . 'germany/services'
        )
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('server.slug', 'germany')
            ->assertJsonPath('data', null);
    }

    public function test_invalid_service_status_becomes_unknown(): void
    {
        $this->authenticate();

        $this->createServer('germany', 'ssh');

        $snapshot = $this->snapshot('invalid-state');

        $snapshot['services'][0]['uptime_seconds'] = -50;

        Cache::put(
            'server-status:servers:germany:services:latest',
            $snapshot,
            now()->addMinutes(3),
        );

        $this->getJson(
            self::ENDPOINT . 'germany/services'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.services.0.status',
                'unknown',
            )
            ->assertJsonPath(
                'data.services.0.uptime_seconds',
                0,
            );
    }

    public function test_disabled_server_returns_404(): void
    {
        $this->authenticate();

        $this->createServer(
            'germany',
            'ssh',
            false,
        );

        $this->getJson(
            self::ENDPOINT . 'germany/services'
        )->assertNotFound();
    }

    public function test_unknown_server_returns_404(): void
    {
        $this->authenticate();

        $this->getJson(
            self::ENDPOINT . 'unknown/services'
        )->assertNotFound();
    }

    public function test_inactive_user_cannot_read_services(): void
    {
        $this->createServer('iran', 'local');

        $this->authenticate(false);

        $this->getJson(
            self::ENDPOINT . 'iran/services'
        )->assertForbidden();
    }

    public function test_unverified_user_cannot_read_services(): void
    {
        $this->createServer('iran', 'local');

        Sanctum::actingAs(
            User::factory()->unverified()->create()
        );

        $this->getJson(
            self::ENDPOINT . 'iran/services'
        )->assertForbidden();
    }


    public function test_expired_services_snapshot_returns_pending(): void
    {
        $this->authenticate();

        $this->createServer('germany', 'ssh');

        $snapshot = $this->snapshot('active');

        $snapshot['checked_at'] = now()
            ->subMinutes(5)
            ->utc()
            ->toIso8601String();

        Cache::put(
            'server-status:servers:germany:services:latest',
            $snapshot,
            now()->addMinutes(3),
        );

        $this->getJson(
            self::ENDPOINT . 'germany/services'
        )
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('data', null);
    }

    public function test_invalid_services_timestamp_returns_pending(): void
    {
        $this->authenticate();

        $this->createServer('germany', 'ssh');

        $snapshot = $this->snapshot('active');

        $snapshot['checked_at'] = 'invalid-date';

        Cache::put(
            'server-status:servers:germany:services:latest',
            $snapshot,
            now()->addMinutes(3),
        );

        $this->getJson(
            self::ENDPOINT . 'germany/services'
        )
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('data', null);
    }

    public function test_future_services_timestamp_returns_pending(): void
    {
        $this->authenticate();

        $this->createServer('germany', 'ssh');

        $snapshot = $this->snapshot('active');

        $snapshot['checked_at'] = now()
            ->addMinutes(5)
            ->utc()
            ->toIso8601String();

        Cache::put(
            'server-status:servers:germany:services:latest',
            $snapshot,
            now()->addMinutes(3),
        );

        $this->getJson(
            self::ENDPOINT . 'germany/services'
        )
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('data', null);
    }


    private function authenticate(
        bool $active = true,
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

    private function snapshot(
        string $status,
    ): array {
        return [
            'services' => [
                [
                    'key' => 'nginx',
                    'name' => 'Nginx',
                    'status' => $status,
                    'uptime_seconds' => 3600,
                ],
            ],
            'checked_at' => now()
                ->utc()
                ->toIso8601String(),
        ];
    }
}
