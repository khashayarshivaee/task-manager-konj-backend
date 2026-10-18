<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\VpnUser;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class VpnTelegramConnectionTest extends TestCase
{
    use RefreshDatabase;

    private function endpoint(VpnUser $vpnUser): string
    {
        return "/api/vpn/users/{$vpnUser->id}/telegram";
    }

    public function test_guest_cannot_access_telegram_connection(): void
    {
        $vpnUser = $this->createVpnUser();

        $this->getJson(
            $this->endpoint($vpnUser),
        )->assertUnauthorized();

        $this->deleteJson(
            $this->endpoint($vpnUser),
        )->assertUnauthorized();
    }

    public function test_other_users_cannot_manage_telegram_connection(): void
    {
        $member = User::factory()->create([
            'email' => 'member@example.test',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $vpnUser = $this->createVpnUser();

        $this->actingAs($member);

        $this->getJson(
            $this->endpoint($vpnUser),
        )->assertForbidden();

        $this->deleteJson(
            $this->endpoint($vpnUser),
        )->assertForbidden();
    }

    public function test_admin_sees_disconnected_status(): void
    {
        $admin = $this->createAdmin();

        $vpnUser = $this->createVpnUser();

        $this->actingAs($admin)
            ->getJson(
                $this->endpoint($vpnUser),
            )
            ->assertOk()
            ->assertJsonPath(
                'data.vpn_user_id',
                $vpnUser->id,
            )
            ->assertJsonPath(
                'data.telegram.connected',
                false,
            )
            ->assertJsonPath(
                'data.telegram.username',
                null,
            );
    }

    public function test_admin_sees_connected_telegram_account(): void
    {
        $admin = $this->createAdmin();

        $vpnUser = $this->createVpnUser();

        $this->createConnection($vpnUser);

        $this->actingAs($admin)
            ->getJson(
                $this->endpoint($vpnUser),
            )
            ->assertOk()
            ->assertJsonPath(
                'data.telegram.connected',
                true,
            )
            ->assertJsonPath(
                'data.telegram.username',
                'konjtester',
            );

        $this->assertDatabaseHas(
            'vpn_telegram_connections',
            [
                'vpn_user_id' => $vpnUser->id,
                'is_active' => true,
            ],
        );
    }

    public function test_disconnecting_telegram_does_not_disable_vpn(): void
    {
        $admin = $this->createAdmin();

        $vpnUser = $this->createVpnUser();

        $this->createConnection($vpnUser);

        $token = Str::random(48);

        DB::table(
            'vpn_telegram_link_tokens',
        )->insert([
            'vpn_user_id' => $vpnUser->id,
            'token_hash' => hash(
                'sha256',
                $token,
            ),
            'created_by' => $admin->id,
            'expires_at' => now()->addMinutes(30),
            'used_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->deleteJson(
                $this->endpoint($vpnUser),
            )
            ->assertOk()
            ->assertJsonPath(
                'data.telegram.connected',
                false,
            );

        /*
         * Telegram must be disconnected.
         */
        $this->assertDatabaseHas(
            'vpn_telegram_connections',
            [
                'vpn_user_id' => $vpnUser->id,
                'is_active' => false,
            ],
        );

        /*
         * Actual VPN account stays active.
         */
        $this->assertDatabaseHas(
            'vpn_users',
            [
                'id' => $vpnUser->id,
                'is_active' => true,
            ],
        );

        /*
         * Outstanding invitation expires.
         */
        $expiresAt = DB::table(
            'vpn_telegram_link_tokens',
        )
            ->where(
                'token_hash',
                hash('sha256', $token),
            )
            ->value('expires_at');

        $this->assertNotNull($expiresAt);

        $this->assertTrue(
            CarbonImmutable::parse(
                $expiresAt,
                'UTC',
            )->lessThanOrEqualTo(
                CarbonImmutable::now('UTC'),
            ),
        );

        /*
         * Status endpoint must now
         * report disconnected.
         */
        $this->getJson(
            $this->endpoint($vpnUser),
        )
            ->assertOk()
            ->assertJsonPath(
                'data.telegram.connected',
                false,
            );
    }

    public function test_disconnect_without_existing_connection_is_safe(): void
    {
        $admin = $this->createAdmin();

        $vpnUser = $this->createVpnUser();

        $this->actingAs($admin)
            ->deleteJson(
                $this->endpoint($vpnUser),
            )
            ->assertOk()
            ->assertJsonPath(
                'data.telegram.connected',
                false,
            );

        $this->assertDatabaseCount(
            'vpn_telegram_connections',
            0,
        );

        $this->assertDatabaseHas(
            'vpn_users',
            [
                'id' => $vpnUser->id,
                'is_active' => true,
            ],
        );
    }

    private function createAdmin(): User
    {
        return User::factory()->create([
            'email' => 'khashayarshivaee@gmail.com',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    private function createVpnUser(): VpnUser
    {
        return VpnUser::query()->create([
            'name' => 'ABBAS',
            'uuid' => (string) Str::uuid(),
            'xray_email' =>
                Str::uuid() . '@example.test',
            'is_active' => true,
            'flow' => 'xtls-rprx-vision',
        ]);
    }

    private function createConnection(
        VpnUser $vpnUser,
    ): void {
        DB::table(
            'vpn_telegram_connections',
        )->insert([
            'vpn_user_id' => $vpnUser->id,

            'telegram_chat_id' => 123456,

            'telegram_user_id' => 123456,

            'telegram_username' => 'konjtester',

            'is_active' => true,

            'connected_at' => now(),

            'created_at' => now(),

            'updated_at' => now(),
        ]);
    }
}
