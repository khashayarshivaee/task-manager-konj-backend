<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class VpnUsageAlertAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_vpn_alerts(): void
    {
        $this->getJson(
            '/api/vpn/usage/alerts',
        )->assertUnauthorized();

        $this->getJson(
            '/api/vpn/usage/alerts/unread-count',
        )->assertUnauthorized();
    }

    public function test_other_users_cannot_access_vpn_alerts(): void
    {
        $user = $this->createUser(
            'other@example.test',
        );

        $this->actingAs($user);

        $this->getJson(
            '/api/vpn/usage/alerts',
        )->assertForbidden();

        $this->getJson(
            '/api/vpn/usage/alerts/unread-count',
        )->assertForbidden();

        $this->patchJson(
            '/api/vpn/usage/alerts/read-all',
        )->assertForbidden();

        $this->patchJson(
            '/api/vpn/usage/alerts/1/read',
        )->assertForbidden();
    }

    public function test_admin_can_read_only_own_alerts(): void
    {
        $admin = $this->createUser(
            'khashayarshivaee@gmail.com',
        );

        $other = $this->createUser(
            'other@example.test',
        );

        $ownAlertId = $this->createAlert($admin);
        $otherAlertId = $this->createAlert($other);

        $this->actingAs($admin);

        $this->getJson(
            '/api/vpn/usage/alerts',
        )
            ->assertOk()
            ->assertJsonPath(
                'data.unread_count',
                1,
            )
            ->assertJsonCount(
                1,
                'data.alerts',
            )
            ->assertJsonPath(
                'data.alerts.0.id',
                $ownAlertId,
            );

        // Cannot read another user's alert.
        $this->patchJson(
            "/api/vpn/usage/alerts/{$otherAlertId}/read",
        )->assertNotFound();

        // Can mark own alert as read.
        $this->patchJson(
            "/api/vpn/usage/alerts/{$ownAlertId}/read",
        )
            ->assertOk()
            ->assertJsonPath(
                'data.unread_count',
                0,
            );

        $this->assertDatabaseHas(
            'vpn_usage_alerts',
            [
                'id' => $ownAlertId,
                'recipient_user_id' => $admin->id,
            ],
        );

        $this->assertNotNull(
            DB::table('vpn_usage_alerts')
                ->where('id', $ownAlertId)
                ->value('read_at'),
        );

        $this->assertNull(
            DB::table('vpn_usage_alerts')
                ->where('id', $otherAlertId)
                ->value('read_at'),
        );
    }

    public function test_read_all_only_updates_admin_alerts(): void
    {
        $admin = $this->createUser(
            'khashayarshivaee@gmail.com',
        );

        $other = $this->createUser(
            'other@example.test',
        );

        $ownAlertId = $this->createAlert($admin);
        $otherAlertId = $this->createAlert($other);

        $this->actingAs($admin);

        $this->patchJson(
            '/api/vpn/usage/alerts/read-all',
        )
            ->assertOk()
            ->assertJsonPath(
                'data.unread_count',
                0,
            );

        $this->assertNotNull(
            DB::table('vpn_usage_alerts')
                ->where('id', $ownAlertId)
                ->value('read_at'),
        );

        $this->assertNull(
            DB::table('vpn_usage_alerts')
                ->where('id', $otherAlertId)
                ->value('read_at'),
        );
    }

    private function createUser(string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    private function createAlert(User $recipient): int
    {
        return (int) DB::table('vpn_usage_alerts')
            ->insertGetId([
                'vpn_user_id' => null,

                'recipient_user_id' =>
                    $recipient->id,

                'vpn_user_name' =>
                    'Test VPN',

                'usage_date' =>
                    now('UTC')->toDateString(),

                'threshold_gb' => 3,

                'total_bytes' =>
                    3 * 1024 * 1024 * 1024,

                'read_at' => null,
                'push_sent_at' => null,

                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
