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

    public function test_allowed_user_can_view_server_connections(): void
    {
        Process::fake([
            self::READER_COMMAND => Process::result(
                output: json_encode(
                    [
                        'summary' => [
                            'inbound_tcp_connections' => 8,
                            'unique_client_ips' => 3,
                            'ssh_vpn_sessions' => 1,
                            'ssh_admin_sessions' => 1,
                            'ssh_other_connections' => 0,
                            'ssh_preauth_connections' => 0,
                            'outline_tcp_client_ips' => 2,
                            'outline_tcp_connections' => 3,
                            'outline_udp_associations' => 1,
                            'outline_keys' => 1,
                            'outline_ports' => 2,
                            'web_client_ips' => 1,
                            'web_connections' => 3,
                            'other_connections' => 0,
                        ],
                        'sessions' => [
                            [
                                'pid' => 2649274,
                                'ssh_user' => 'root',
                                'client_ip' => '188.229.90.173',
                                'client_port' => 35047,
                                'elapsed_seconds' => 68,
                                'active_connections' => 49,
                            ],
                        ],
                        'connections' => [
                            [
                                'type' => 'ssh_vpn',
                                'protocol' => 'tcp',
                                'service' => 'sshd',
                                'client_ip' => '188.229.90.173',
                                'client_port' => 35047,
                                'server_port' => 22,
                                'pid' => 2649274,
                                'ssh_user' => 'root',
                                'elapsed_seconds' => 68,
                                'proxied_connections' => 49,
                            ],
                            [
                                'type' => 'ssh_admin',
                                'protocol' => 'tcp',
                                'service' => 'sshd',
                                'client_ip' => '188.229.89.46',
                                'client_port' => 45426,
                                'server_port' => 22,
                                'pid' => 2638579,
                                'ssh_user' => 'deploy',
                                'elapsed_seconds' => 8800,
                            ],
                            [
                                'type' => 'outline',
                                'protocol' => 'tcp',
                                'service' => 'outline-ss-serv',
                                'client_ip' => '172.80.150.4',
                                'client_port' => 28706,
                                'server_port' => 9453,
                                'pid' => 1444,
                            ],
                            [
                                'type' => 'outline',
                                'protocol' => 'tcp',
                                'service' => 'outline-ss-serv',
                                'client_ip' => '178.131.169.196',
                                'client_port' => 49971,
                                'server_port' => 9453,
                                'pid' => 1444,
                            ],
                            [
                                'type' => 'outline',
                                'protocol' => 'tcp',
                                'service' => 'outline-ss-serv',
                                'client_ip' => '172.80.150.4',
                                'client_port' => 28705,
                                'server_port' => 9453,
                                'pid' => 1444,
                            ],
                            [
                                'type' => 'web',
                                'protocol' => 'tcp',
                                'service' => 'nginx',
                                'client_ip' => '188.229.89.46',
                                'client_port' => 37967,
                                'server_port' => 443,
                                'pid' => 2311706,
                            ],
                            [
                                'type' => 'web',
                                'protocol' => 'tcp',
                                'service' => 'nginx',
                                'client_ip' => '188.229.89.46',
                                'client_port' => 34973,
                                'server_port' => 443,
                                'pid' => 2311706,
                            ],
                            [
                                'type' => 'web',
                                'protocol' => 'tcp',
                                'service' => 'nginx',
                                'client_ip' => '188.229.89.46',
                                'client_port' => 37985,
                                'server_port' => 443,
                                'pid' => 2311706,
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

        $response = $this->getJson('/api/vpn/sessions');

        $response
            ->assertOk()
            ->assertJsonPath('data.total', 8)
            ->assertJsonPath(
                'data.summary.ssh_vpn_sessions',
                1,
            )
            ->assertJsonPath(
                'data.summary.ssh_admin_sessions',
                1,
            )
            ->assertJsonPath(
                'data.summary.outline_tcp_client_ips',
                2,
            )
            ->assertJsonPath(
                'data.summary.outline_tcp_connections',
                3,
            )
            ->assertJsonPath(
                'data.summary.outline_udp_associations',
                1,
            )
            ->assertJsonPath(
                'data.summary.web_connections',
                3,
            )
            ->assertJsonCount(
                1,
                'data.sessions',
            )
            ->assertJsonCount(
                8,
                'data.connections',
            )
            ->assertJsonPath(
                'data.connections.0.type',
                'ssh_vpn',
            )
            ->assertJsonPath(
                'data.connections.2.type',
                'outline',
            )
            ->assertJsonPath(
                'data.connections.5.type',
                'web',
            );

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

    public function test_api_returns_service_unavailable_for_invalid_reader_payload(): void
    {
        Process::fake([
            self::READER_COMMAND => Process::result(
                output: json_encode(
                    [
                        'sessions' => [],
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
            ->assertStatus(503)
            ->assertExactJson([
                'message' => 'Unable to load VPN sessions.',
            ]);

        Process::assertRan(self::READER_COMMAND);
    }
}
