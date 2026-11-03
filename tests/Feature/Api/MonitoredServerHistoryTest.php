<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\MonitoredServer;
use App\Models\MonitoredServerSample;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class MonitoredServerHistoryTest extends TestCase
{
    use RefreshDatabase;

    private const URL =
        '/api/server-status/servers/';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(
            CarbonImmutable::parse(
                '2026-11-03 12:00:00',
                'UTC',
            ),
        );
    }

    public function test_guest_cannot_read_history(): void
    {
        $this->createServer('iran');

        $this->getJson(
            self::URL . 'iran/history',
        )->assertUnauthorized();
    }

    public function test_inactive_user_cannot_read_history(): void
    {
        $this->authenticate(false);
        $this->createServer('iran');

        $this->getJson(
            self::URL . 'iran/history',
        )->assertForbidden();
    }

    public function test_unverified_user_cannot_read_history(): void
    {
        Sanctum::actingAs(
            User::factory()->unverified()->create(),
        );

        $this->createServer('iran');

        $this->getJson(
            self::URL . 'iran/history',
        )->assertForbidden();
    }

    public function test_disabled_server_returns_404(): void
    {
        $this->authenticate();

        $this->createServer('company', false);

        $this->getJson(
            self::URL . 'company/history',
        )->assertNotFound();
    }

    public function test_unknown_server_returns_404(): void
    {
        $this->authenticate();

        $this->getJson(
            self::URL . 'unknown/history',
        )->assertNotFound();
    }

    public function test_empty_history_returns_pending(): void
    {
        $this->authenticate();
        $this->createServer('germany');

        $this->getJson(
            self::URL . 'germany/history?range=24h',
        )
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('sample_count', 0)
            ->assertJsonPath(
                'summary.peak_cpu_percent',
                null,
            )
            ->assertJsonCount(0, 'data');
    }

    public function test_invalid_range_returns_422(): void
    {
        $this->authenticate();
        $this->createServer('iran');

        $this->getJson(
            self::URL . 'iran/history?range=invalid',
        )->assertStatus(422);
    }

    public function test_hourly_aggregation_and_isolation(): void
    {
        $this->authenticate();

        $germany = $this->createServer('germany');
        $company = $this->createServer('company');

        $first = CarbonImmutable::parse(
            '2026-11-03 11:10:00',
            'UTC',
        );

        $second = CarbonImmutable::parse(
            '2026-11-03 11:35:00',
            'UTC',
        );

        $this->createSample(
            $germany,
            $first,
            10.0,
            30.0,
            40.0,
        );

        $this->createSample(
            $germany,
            $second,
            90.0,
            50.0,
            60.0,
        );

        // Another server must not affect Germany.
        $this->createSample(
            $company,
            $first,
            99.0,
            80.0,
            70.0,
        );

        $this->getJson(
            self::URL . 'germany/history?range=7d',
        )
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('server.slug', 'germany')
            ->assertJsonPath('interval_seconds', 3600)
            ->assertJsonPath('sample_count', 2)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.cpu_avg', 50)
            ->assertJsonPath('data.0.cpu_max', 90)
            ->assertJsonPath('data.0.memory_avg', 40)
            ->assertJsonPath('data.0.disk_avg', 50)
            ->assertJsonPath(
                'summary.peak_cpu_percent',
                90,
            )
            ->assertJsonPath(
                'summary.peak_cpu_at',
                $second->toIso8601String(),
            );
    }

    public function test_old_samples_are_excluded(): void
    {
        $this->authenticate();

        $server = $this->createServer('iran');

        $this->createSample(
            $server,
            CarbonImmutable::now('UTC')
                ->subHours(25),
            95.0,
        );

        $this->createSample(
            $server,
            CarbonImmutable::now('UTC')
                ->subMinutes(10),
            20.0,
        );

        $this->getJson(
            self::URL . 'iran/history?range=24h',
        )
            ->assertOk()
            ->assertJsonPath('sample_count', 1)
            ->assertJsonPath('interval_seconds', 300)
            ->assertJsonPath(
                'summary.peak_cpu_percent',
                20,
            )
            ->assertJsonCount(1, 'data');
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
        string $slug,
        bool $enabled = true,
    ): MonitoredServer {
        return MonitoredServer::query()->create([
            'slug' => $slug,
            'name' => ucfirst($slug) . ' Server',
            'region' => null,
            'driver' =>
                $slug === 'iran' ? 'local' : 'ssh',
            'is_enabled' => $enabled,
            'sort_order' => 0,
        ]);
    }

    private function createSample(
        MonitoredServer $server,
        CarbonImmutable $time,
        float $cpu,
        float $memory = 40.0,
        float $disk = 30.0,
    ): void {
        MonitoredServerSample::query()->create([
            'monitored_server_id' => $server->id,
            'cpu_percent' => $cpu,
            'memory_percent' => $memory,
            'memory_total_bytes' => 8000000000,
            'memory_used_bytes' => 3200000000,
            'disk_percent' => $disk,
            'disk_total_bytes' => 100000000000,
            'disk_used_bytes' => 30000000000,
            'uptime_seconds' => 86400,
            'measured_at' => $time,
        ]);
    }
}
