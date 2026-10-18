<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\VpnUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_URL =
        '/api/telegram/webhook';

    private const SECRET =
        'konj-test-telegram-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'services.telegram.webhook_secret',
            self::SECRET,
        );
    }

    public function test_request_without_secret_is_rejected(): void
    {
        $this->postJson(
            self::WEBHOOK_URL,
            $this->telegramUpdate('/start'),
        )->assertForbidden();

        $this->assertDatabaseCount(
            'vpn_telegram_connections',
            0,
        );
    }

    public function test_invalid_secret_is_rejected(): void
    {
        $this->withHeader(
            'X-Telegram-Bot-Api-Secret-Token',
            'wrong-secret',
        )->postJson(
            self::WEBHOOK_URL,
            $this->telegramUpdate('/start'),
        )->assertForbidden();
    }

    public function test_valid_invitation_connects_telegram_account(): void
    {
        $vpnUser = $this->createVpnUser();

        $token = $this->createInvitation(
            $vpnUser,
        );

        $this->sendTelegramMessage(
            '/start ' . $token,
        )
            ->assertOk()
            ->assertJsonPath(
                'method',
                'sendMessage',
            );

        $this->assertDatabaseHas(
            'vpn_telegram_connections',
            [
                'vpn_user_id' => $vpnUser->id,
                'telegram_chat_id' => 123456,
                'telegram_user_id' => 123456,
                'telegram_username' => 'konjtester',
                'is_active' => true,
            ],
        );

        $this->assertDatabaseHas(
            'vpn_telegram_link_tokens',
            [
                'token_hash' => hash(
                    'sha256',
                    $token,
                ),
            ],
        );

        $this->assertNotNull(
            DB::table(
                'vpn_telegram_link_tokens',
            )
                ->where(
                    'token_hash',
                    hash('sha256', $token),
                )
                ->value('used_at'),
        );
    }

    public function test_invitation_cannot_be_reused(): void
    {
        $vpnUser = $this->createVpnUser();

        $token = $this->createInvitation(
            $vpnUser,
        );

        $this->sendTelegramMessage(
            '/start ' . $token,
        )->assertOk();

        /*
         * A different Telegram user
         * attempts to reuse the link.
         */
        $this->sendTelegramMessage(
            '/start ' . $token,
            999999,
        )->assertOk();

        $this->assertDatabaseCount(
            'vpn_telegram_connections',
            1,
        );

        $this->assertDatabaseHas(
            'vpn_telegram_connections',
            [
                'vpn_user_id' => $vpnUser->id,
                'telegram_chat_id' => 123456,
            ],
        );
    }

    public function test_expired_invitation_is_rejected(): void
    {
        $vpnUser = $this->createVpnUser();

        $token = $this->createInvitation(
            $vpnUser,
            true,
        );

        $this->sendTelegramMessage(
            '/start ' . $token,
        )->assertOk();

        $this->assertDatabaseCount(
            'vpn_telegram_connections',
            0,
        );
    }

    public function test_group_messages_cannot_connect_users(): void
    {
        $vpnUser = $this->createVpnUser();

        $token = $this->createInvitation(
            $vpnUser,
        );

        $payload = $this->telegramUpdate(
            '/start ' . $token,
        );

        $payload['message']['chat']['type'] =
            'group';

        $this->withHeader(
            'X-Telegram-Bot-Api-Secret-Token',
            self::SECRET,
        )->postJson(
            self::WEBHOOK_URL,
            $payload,
        )->assertOk();

        $this->assertDatabaseCount(
            'vpn_telegram_connections',
            0,
        );
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

    private function createInvitation(
        VpnUser $vpnUser,
        bool $expired = false,
    ): string {
        $token = Str::random(48);

        DB::table(
            'vpn_telegram_link_tokens',
        )->insert([
            'vpn_user_id' => $vpnUser->id,
            'token_hash' => hash(
                'sha256',
                $token,
            ),
            'expires_at' => $expired
                ? now()->subMinute()
                : now()->addMinutes(30),
            'used_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $token;
    }

    private function sendTelegramMessage(
        string $text,
        int $chatId = 123456,
    ): \Illuminate\Testing\TestResponse {
        return $this->withHeader(
            'X-Telegram-Bot-Api-Secret-Token',
            self::SECRET,
        )->postJson(
            self::WEBHOOK_URL,
            $this->telegramUpdate(
                $text,
                $chatId,
            ),
        );
    }

    private function telegramUpdate(
        string $text,
        int $chatId = 123456,
    ): array {
        return [
            'update_id' => 12345,
            'message' => [
                'message_id' => 1,
                'chat' => [
                    'id' => $chatId,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => $chatId,
                    'is_bot' => false,
                    'username' => 'konjtester',
                ],
                'text' => $text,
            ],
        ];
    }
}
