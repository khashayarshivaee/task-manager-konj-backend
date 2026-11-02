<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\MonitoredServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class MonitoredServerTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/server-status/servers';

    public function test_guest_cannot_list_servers(): void
    {
        $this->getJson(self::ENDPOINT)
            ->assertUnauthorized();
    }

    public function test_verified_user_can_list_enabled_servers(): void
    {
        $germany = MonitoredServer::query()->create([
            'slug' => 'germany',
            'name' => 'Germany Server',
            'region' => 'Germany',
            'driver' => 'ssh',
            'is_enabled' => true,
            'sort_order' => 20,
        ]);

        $iran = MonitoredServer::query()->create([
            'slug' => 'iran',
            'name' => 'Iran Server',
            'region' => 'Iran',
            'driver' => 'local',
            'is_enabled' => true,
            'sort_order' => 10,
        ]);

        MonitoredServer::query()->create([
            'slug' => 'disabled',
            'name' => 'Disabled Server',
            'region' => 'Test',
            'driver' => 'ssh',
            'is_enabled' => false,
            'sort_order' => 0,
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    [
                        'id' => $iran->id,
                        'slug' => 'iran',
                        'name' => 'Iran Server',
                        'region' => 'Iran',
                    ],
                    [
                        'id' => $germany->id,
                        'slug' => 'germany',
                        'name' => 'Germany Server',
                        'region' => 'Germany',
                    ],
                ],
            ]);
    }

    public function test_unverified_user_cannot_list_servers(): void
    {
        $user = User::factory()
            ->unverified()
            ->create();

        Sanctum::actingAs($user);

        $this->getJson(self::ENDPOINT)
            ->assertForbidden();
    }

    public function test_inactive_user_cannot_list_servers(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
        ]);

        Sanctum::actingAs($user);

        $this->getJson(self::ENDPOINT)
            ->assertForbidden();
    }

    public function test_empty_server_list_returns_empty_array(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertExactJson([
                'data' => [],
            ]);
    }
}
