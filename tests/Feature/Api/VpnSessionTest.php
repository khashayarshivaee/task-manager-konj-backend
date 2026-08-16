<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VpnSessionTest extends TestCase
{
    use RefreshDatabase;

    private const READER_COMMAND =
        'sudo -n /usr/local/bin/task-manager-vpn-sessions';

    protected function setUp(): void
    {
        parent::setUp();

        Process::preventStrayProcesses();
    }

    public function test_guest_cannot_access_vpn_sessions(): void
    {
        Process::fake();

        $this->getJson('/api/vpn/sessions')
            ->assertUnauthorized();

        Process::assertDidntRun(self::READER_COMMAND);
    }

    public function test_other_authenticated_user_cannot_access_vpn_sessions(): void
    {
        Process::fake();

        $user = User::factory()->create([
            'email' => 'other@example.com',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        Sanctum::actingAs(
            $user,
            ['*'],
        );

        $this->getJson('/api/vpn/sessions')
            ->assertForbidden();

        Process::assertDidntRun(self::READER_COMMAND);
    }

    public function test_allowed_user_can_view_active_vpn_sessions(): void
    {
        Process::fake([
            self::READER_COMMAND => Process::result(
                output: json_encode(
                    [
                        'sessions' => [
                            [
                                'pid' => 2644824,
                                'ssh_user' => 'root',
                                'client_ip' => '89.199.15.27',
                                'client_port' => 6452,
                                'elapsed_seconds' => 754,
                                'active_connections' => 8,
                            ],
                        ],
                    ],
                    JSON_THROW_ON_ERROR,
                ),
            ),
        ]);

        $user = User::factory()->create([
            'email' => 'khashayarshivaee@gmail.com',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        Sanctum::actingAs(
            $user,
            ['*'],
        );

        $this->getJson('/api/vpn/sessions')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'total' => 1,
                    'sessions' => [
                        [
                            'pid' => 2644824,
                            'ssh_user' => 'root',
                            'client_ip' => '89.199.15.27',
                            'client_port' => 6452,
                            'elapsed_seconds' => 754,
                            'active_connections' => 8,
                        ],
                    ],
                ],
            ]);

        Process::assertRan(self::READER_COMMAND);
    }

    public function test_api_returns_service_unavailable_when_reader_fails(): void
    {
        Process::fake([
            self::READER_COMMAND => Process::result(
                errorOutput: 'Reader unavailable.',
                exitCode: 1,
            ),
        ]);

        $user = User::factory()->create([
            'email' => 'khashayarshivaee@gmail.com',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        Sanctum::actingAs(
            $user,
            ['*'],
        );

        $this->getJson('/api/vpn/sessions')
            ->assertStatus(503)
            ->assertExactJson([
                'message' => 'Unable to load VPN sessions.',
            ]);

        Process::assertRan(self::READER_COMMAND);
    }
}
