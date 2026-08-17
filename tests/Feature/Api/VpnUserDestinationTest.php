<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\VpnUser;
use App\Models\VpnUserDestination;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VpnUserDestinationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_view_vpn_destinations(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $vpnUser = VpnUser::query()->create([
            'name' => 'Test iPhone',
            'uuid' => '11111111-1111-4111-8111-111111111111',
            'xray_email' => 'vpn-test@task-manager.local',
            'is_active' => true,
            'flow' => 'xtls-rprx-vision',
            'created_by' => $user->id,
        ]);

        VpnUserDestination::query()->create([
            'vpn_user_id' => $vpnUser->id,
            'destination' => 'instagram.com',
            'connection_count' => 12,
            'total_duration_seconds' => 3600,
        ]);

        $response = $this->getJson(
            "/api/vpn/users/{$vpnUser->id}/destinations",
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.destination',
                'instagram.com',
            )
            ->assertJsonPath(
                'data.0.connection_count',
                12,
            )
            ->assertJsonPath(
                'data.0.total_duration_seconds',
                3600,
            );
    }
}
