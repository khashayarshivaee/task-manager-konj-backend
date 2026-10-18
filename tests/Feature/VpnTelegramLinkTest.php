<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\VpnUser;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class VpnTelegramLinkTest extends TestCase
{
    use RefreshDatabase;

    private function endpoint(VpnUser $vpnUser): string
    {
        return "/api/vpn/users/{$vpnUser->id}/telegram/link";
    }

    public function test_guest_cannot_generate_link(): void
    {
        $vpnUser = $this->createVpnUser();

        $this->postJson(
            $this->endpoint($vpnUser),
        )->assertUnauthorized();

        $this->assertDatabaseCount(
            'vpn_telegram_link_tokens',
            0,
        );
    }

    public function test_other_users_cannot_generate_link(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.test',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $vpnUser = $this->createVpnUser();

        $this->actingAs($user)
            ->postJson($this->endpoint($vpnUser))
            ->assertForbidden();

        $this->assertDatabaseCount(
            'vpn_telegram_link_tokens',
            0,
        );
    }

    public function test_admin_generates_secure_link(): void
    {
        $admin = $this->createAdmin();
        $vpnUser = $this->createVpnUser();

        $response = $this->actingAs($admin)
            ->postJson($this->endpoint($vpnUser))
            ->assertCreated()
            ->assertJsonPath(
                'data.vpn_user_id',
                $vpnUser->id,
            )
            ->assertJsonPath(
                'data.bot_username',
                'konj_team_bot',
            );

        $link = $response->json('data.link');

        $this->assertIsString($link);

        $this->assertStringStartsWith(
            'https://t.me/konj_team_bot?start=',
            $link,
        );

        $token = $this->extractToken($response);

        $this->assertMatchesRegularExpression(
            '/^[A-Za-z0-9_-]{32,64}$/',
            $token,
        );

        $hash = hash('sha256', $token);

        $this->assertDatabaseHas(
            'vpn_telegram_link_tokens',
            [
                'vpn_user_id' => $vpnUser->id,
                'token_hash' => $hash,
                'created_by' => $admin->id,
                'used_at' => null,
            ],
        );

        /*
         * Raw invitation tokens must
         * never be stored in the database.
         */
        $this->assertDatabaseMissing(
            'vpn_telegram_link_tokens',
            [
                'token_hash' => $token,
            ],
        );

        $expiresAt = DB::table(
            'vpn_telegram_link_tokens',
        )
            ->where('token_hash', $hash)
            ->value('expires_at');

        $this->assertNotNull($expiresAt);

        /*
         * Invitation expires in
         * approximately 30 minutes.
         */
        $this->assertEqualsWithDelta(
            now()->addMinutes(30)->timestamp,
            CarbonImmutable::parse(
                $expiresAt,
            )->timestamp,
            5,
        );
    }

    public function test_new_link_invalidates_previous_link(): void
    {
        $admin = $this->createAdmin();
        $vpnUser = $this->createVpnUser();

        $this->actingAs($admin);

        $firstResponse = $this->postJson(
            $this->endpoint($vpnUser),
        )->assertCreated();

        $firstToken = $this->extractToken(
            $firstResponse,
        );

        $secondResponse = $this->postJson(
            $this->endpoint($vpnUser),
        )->assertCreated();

        $secondToken = $this->extractToken(
            $secondResponse,
        );

        $this->assertNotSame(
            $firstToken,
            $secondToken,
        );

        $firstExpiry = DB::table(
            'vpn_telegram_link_tokens',
        )
            ->where(
                'token_hash',
                hash('sha256', $firstToken),
            )
            ->value('expires_at');

        $this->assertNotNull($firstExpiry);

        $this->assertTrue(
            CarbonImmutable::parse(
                $firstExpiry,
            )->lessThanOrEqualTo(
                CarbonImmutable::now('UTC'),
            ),
        );

        $this->assertDatabaseHas(
            'vpn_telegram_link_tokens',
            [
                'token_hash' => hash(
                    'sha256',
                    $secondToken,
                ),
                'used_at' => null,
            ],
        );
    }

    public function test_inactive_vpn_user_cannot_get_link(): void
    {
        $admin = $this->createAdmin();

        $vpnUser = $this->createVpnUser();

        $vpnUser->update([
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->postJson($this->endpoint($vpnUser))
            ->assertStatus(409);

        $this->assertDatabaseCount(
            'vpn_telegram_link_tokens',
            0,
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

    private function extractToken(
        TestResponse $response,
    ): string {
        $link = (string) $response->json(
            'data.link',
        );

        $query = parse_url(
            $link,
            PHP_URL_QUERY,
        );

        parse_str(
            is_string($query) ? $query : '',
            $params,
        );

        $token = $params['start'] ?? null;

        $this->assertIsString($token);

        return $token;
    }
}
