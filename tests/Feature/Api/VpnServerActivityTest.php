<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\VpnUser;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VpnServerActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_server_activity(): void
    {
        $this->getJson('/api/vpn/server-activity')
            ->assertUnauthorized();
    }

    public function test_other_user_cannot_access_server_activity(): void
    {
        $user = User::factory()->create([
            'email' => 'other@example.test',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/vpn/server-activity')
            ->assertForbidden();
    }

    public function test_authorized_user_can_view_server_activity(): void
    {
        $this->travelTo(
            CarbonImmutable::parse(
                '2026-11-01 12:00:00',
                'UTC',
            ),
        );

        config()->set(
            'xray.server',
            'edge.maranonline.com',
        );

        $this->authenticateAsVpnAdmin();

        $admin = $this->createVpnUser(
            'Admin',
            true,
        );

        $abbas = $this->createVpnUser(
            'ABBAS',
            true,
        );

        $disabled = $this->createVpnUser(
            'Disabled',
            false,
        );

        $this->insertDailyUsage(
            $admin,
            4096,
            8192,
            '2026-11-01 11:59:00',
        );

        $this->insertDailyUsage(
            $abbas,
            1024,
            2048,
            '2026-11-01 11:50:00',
        );

        $this->getJson('/api/vpn/server-activity')
            ->assertOk()
            ->assertJsonPath(
                'data.server',
                'edge.maranonline.com',
            )
            ->assertJsonPath(
                'data.recent_window_minutes',
                3,
            )
            ->assertJsonPath(
                'data.summary.total_users',
                3,
            )
            ->assertJsonPath(
                'data.summary.enabled_users',
                2,
            )
            ->assertJsonPath(
                'data.summary.recent_traffic_users',
                1,
            )
            ->assertJsonPath(
                'data.summary.today_uplink_bytes',
                5120,
            )
            ->assertJsonPath(
                'data.summary.today_downlink_bytes',
                10240,
            )
            ->assertJsonCount(
                3,
                'data.users',
            )
            ->assertJsonPath(
                'data.users.0.id',
                $admin->id,
            )
            ->assertJsonPath(
                'data.users.0.has_recent_traffic',
                true,
            )
            ->assertJsonPath(
                'data.users.0.today.total_bytes',
                12288,
            )
            ->assertJsonPath(
                'data.users.1.id',
                $abbas->id,
            )
            ->assertJsonPath(
                'data.users.1.has_recent_traffic',
                false,
            )
            ->assertJsonPath(
                'data.users.2.id',
                $disabled->id,
            )
            ->assertJsonPath(
                'data.users.2.is_active',
                false,
            )
            ->assertJsonPath(
                'data.users.2.has_recent_traffic',
                false,
            )
            ->assertJsonPath(
                'data.users.2.today.total_bytes',
                0,
            );
    }

    public function test_empty_activity_returns_zero_totals(): void
    {
        $this->authenticateAsVpnAdmin();

        $this->getJson('/api/vpn/server-activity')
            ->assertOk()
            ->assertJsonPath(
                'data.summary.total_users',
                0,
            )
            ->assertJsonPath(
                'data.summary.enabled_users',
                0,
            )
            ->assertJsonPath(
                'data.summary.recent_traffic_users',
                0,
            )
            ->assertJsonPath(
                'data.summary.today_uplink_bytes',
                0,
            )
            ->assertJsonPath(
                'data.summary.today_downlink_bytes',
                0,
            )
            ->assertJsonCount(
                0,
                'data.users',
            );
    }

    private function authenticateAsVpnAdmin(): void
    {
        $user = User::factory()->create([
            'email' => 'khashayarshivaee@gmail.com',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);
    }

    private function createVpnUser(
        string $name,
        bool $isActive,
    ): VpnUser {
        $uuid = (string) Str::uuid();

        return VpnUser::query()->create([
            'name' => $name,
            'uuid' => $uuid,
            'xray_email' =>
                'vpn-'.$uuid.'@task-manager.local',
            'is_active' => $isActive,
            'flow' => '',
        ]);
    }

    private function insertDailyUsage(
        VpnUser $user,
        int $upload,
        int $download,
        string $updatedAt,
    ): void {
        DB::table('vpn_user_daily_usages')
            ->insert([
                'vpn_user_id' => $user->id,
                'usage_date' => '2026-11-01',
                'uplink_bytes' => $upload,
                'downlink_bytes' => $download,
                'created_at' => $updatedAt,
                'updated_at' => $updatedAt,
            ]);
    }
}
