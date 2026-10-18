<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\VpnUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class TelegramChatOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private const CHAT_ID = 711222333;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'services.telegram.webhook_secret',
            'test-webhook-secret',
        );
    }

    public function test_active_telegram_account_cannot_connect_to_another_vpn(): void
    {
        $firstVpn = $this->createVpnUser('ABBAS');
        $secondVpn = $this->createVpnUser('ALI');

        $firstToken = $this->createInvitation($firstVpn);
        $secondToken = $this->createInvitation($secondVpn);

        // Connect Telegram to the first VPN.
        $this->startBot($firstToken)
            ->assertOk();

        // Attempt to connect the same chat
        // to a different VPN account.
        $response = $this->startBot($secondToken);

        $response->assertOk();

        $this->assertStringContainsString(
            'already linked',
            (string) $response->json('text'),
        );

        // Original connection must remain intact.
        $this->assertDatabaseHas(
            'vpn_telegram_connections',
            [
                'vpn_user_id' => $firstVpn->id,
                'telegram_chat_id' => self::CHAT_ID,
                'is_active' => true,
            ],
        );

        $this->assertDatabaseMissing(
            'vpn_telegram_connections',
            [
                'vpn_user_id' => $secondVpn->id,
            ],
        );

        // Rejected invitation must remain usable.
        $this->assertDatabaseHas(
            'vpn_telegram_link_tokens',
            [
                'token_hash' => hash('sha256', $secondToken),
                'used_at' => null,
            ],
        );

        $this->assertDatabaseCount(
            'vpn_telegram_connections',
            1,
        );
    }

    public function test_disconnected_account_can_move_to_another_vpn(): void
    {
        $firstVpn = $this->createVpnUser('ABBAS');
        $secondVpn = $this->createVpnUser('ALI');

        $firstToken = $this->createInvitation($firstVpn);
        $secondToken = $this->createInvitation($secondVpn);

        $this->startBot($firstToken)
            ->assertOk();

        // Disconnect the previous account.
        DB::table('vpn_telegram_connections')
            ->where('vpn_user_id', $firstVpn->id)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

        // The same Telegram account
        // can now connect to the second VPN.
        $response = $this->startBot($secondToken);

        $response->assertOk();

        $this->assertStringContainsString(
            "You're connected to KONJ TEAM",
            (string) $response->json('text'),
        );

        $this->assertDatabaseHas(
            'vpn_telegram_connections',
            [
                'vpn_user_id' => $secondVpn->id,
                'telegram_chat_id' => self::CHAT_ID,
                'is_active' => true,
            ],
        );

        // No duplicate Telegram chat ownership.
        $this->assertDatabaseCount(
            'vpn_telegram_connections',
            1,
        );

        // Successful invitation was consumed.
        $this->assertNotNull(
            DB::table('vpn_telegram_link_tokens')
                ->where(
                    'token_hash',
                    hash('sha256', $secondToken),
                )
                ->value('used_at'),
        );
    }

    private function createVpnUser(string $name): VpnUser
    {
        return VpnUser::query()->create([
            'name' => $name,
            'uuid' => (string) Str::uuid(),
            'xray_email' => Str::uuid() . '@example.test',
            'is_active' => true,
            'flow' => 'xtls-rprx-vision',
        ]);
    }

    private function createInvitation(VpnUser $vpnUser): string
    {
        $token = Str::random(48);

        DB::table('vpn_telegram_link_tokens')
            ->insert([
                'vpn_user_id' => $vpnUser->id,
                'token_hash' => hash('sha256', $token),
                'created_by' => null,
                'expires_at' => now()->addMinutes(30),
                'used_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return $token;
    }

    private function startBot(string $token): TestResponse
    {
        return $this->postJson(
            '/api/telegram/webhook',
            [
                'update_id' => 10001,
                'message' => [
                    'message_id' => 1,
                    'chat' => [
                        'id' => self::CHAT_ID,
                        'type' => 'private',
                    ],
                    'from' => [
                        'id' => self::CHAT_ID,
                        'is_bot' => false,
                        'first_name' => 'Test',
                        'username' => 'konjtester',
                    ],
                    'text' => '/start ' . $token,
                ],
            ],
            [
                'X-Telegram-Bot-Api-Secret-Token'
                => 'test-webhook-secret',
            ],
        );
    }
}
