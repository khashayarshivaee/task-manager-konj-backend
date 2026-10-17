<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\VpnUser;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VpnDailyUsageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_daily_usage(): void
    {
        $this->getJson('/api/vpn/usage/daily')
            ->assertUnauthorized();
    }

    public function test_other_users_cannot_access_daily_usage(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.test',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/vpn/usage/daily')
            ->assertForbidden();
    }

    public function test_authorized_user_can_read_daily_usage(): void
    {
        $this->authenticateAsVpnAdmin();

        $vpnUser = $this->createVpnUser();

        $today = CarbonImmutable::today('UTC')
            ->toDateString();

        DB::table('vpn_user_daily_usages')->insert([
            'vpn_user_id' => $vpnUser->id,
            'usage_date' => $today,
            'downlink_bytes' => 1500000,
            'uplink_bytes' => 500000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson(
            '/api/vpn/usage/daily?period=today',
        )
            ->assertOk()
            ->assertJsonPath('data.period', 'today')
            ->assertJsonPath('data.timezone', 'UTC')
            ->assertJsonPath('data.users.0.id', $vpnUser->id)
            ->assertJsonPath(
                'data.users.0.downlink_bytes',
                1500000,
            )
            ->assertJsonPath(
                'data.users.0.uplink_bytes',
                500000,
            )
            ->assertJsonPath(
                'data.users.0.total_bytes',
                2000000,
            )
            ->assertJsonPath(
                'data.users.0.days.0.date',
                $today,
            );
    }

    public function test_last_seven_days_includes_empty_days(): void
    {
        $this->authenticateAsVpnAdmin();

        $vpnUser = $this->createVpnUser();

        DB::table('vpn_user_daily_usages')->insert([
            'vpn_user_id' => $vpnUser->id,
            'usage_date' => CarbonImmutable::today('UTC')
                ->subDay()
                ->toDateString(),
            'downlink_bytes' => 3000,
            'uplink_bytes' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson(
            '/api/vpn/usage/daily?period=last7days',
        )
            ->assertOk()
            ->assertJsonCount(7, 'data.users.0.days')
            ->assertJsonPath(
                'data.users.0.total_bytes',
                4000,
            );
    }

    public function test_invalid_period_is_rejected(): void
    {
        $this->authenticateAsVpnAdmin();

        $this->getJson(
            '/api/vpn/usage/daily?period=invalid',
        )
            ->assertUnprocessable();
    }

    public function test_destinations_require_authentication(): void
    {
        $vpnUser = $this->createVpnUser();

        $this->getJson(
            "/api/vpn/users/{$vpnUser->id}/destinations",
        )
            ->assertUnauthorized();
    }

    private function authenticateAsVpnAdmin(): void
    {
        $user = User::factory()->create([
            'email' => 'khashayarshivaee@gmail.com',
        ]);

        Sanctum::actingAs($user);
    }

    private function createVpnUser(): VpnUser
    {
        return VpnUser::query()->create([
            'name' => 'Usage API Test',
            'uuid' => (string) Str::uuid(),
            'xray_email' => 'usage-api@example.test',
            'is_active' => true,
            'flow' => 'xtls-rprx-vision',
        ]);
    }
}
