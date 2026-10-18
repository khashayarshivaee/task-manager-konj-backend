<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendVpnTelegramUsageNotification;
use App\Models\VpnUser;
use App\Services\TelegramBotService;
use App\Services\VpnTelegramUsageMessageFormatter;
use App\Services\VpnTelegramUsageNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class TelegramReconnectTest extends TestCase
{
    use RefreshDatabase;

    private const GB = 1024 * 1024 * 1024;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'services.telegram.webhook_secret',
            'test-webhook-secret',
        );
    }

    public function test_reconnect_clears_pending_but_preserves_sent_notifications(): void
    {
        $vpnUser = $this->createVpnUser();

        $this->recordUsage(
            $vpnUser,
            6 * self::GB,
        );

        /*
         * Previous Telegram connection.
         */
        DB::table(
            'vpn_telegram_connections',
        )->insert([
            'vpn_user_id' => $vpnUser->id,
            'telegram_chat_id' => 111111,
            'telegram_user_id' => 111111,
            'telegram_username' => 'old_account',
            'is_active' => true,
            'connected_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
         * 3 GB notification was delivered.
         */
        $sentId = $this->createNotification(
            $vpnUser,
            111111,
            3,
            true,
        );

        /*
         * 5 GB notification is still pending.
         */
        $pendingId = $this->createNotification(
            $vpnUser,
            111111,
            5,
            false,
        );

        $token = $this->createLinkToken(
            $vpnUser,
        );

        /*
         * Connect a different Telegram chat.
         */
        $this->startBot(
            $token,
            222222,
        );

        /*
         * Connection must point to
         * the newly linked chat.
         */
        $this->assertDatabaseHas(
            'vpn_telegram_connections',
            [
                'vpn_user_id' => $vpnUser->id,
                'telegram_chat_id' => 222222,
                'is_active' => true,
            ],
        );

        /*
         * Successfully sent history
         * must remain untouched.
         */
        $this->assertNotNull(
            DB::table(
                'vpn_telegram_usage_notifications',
            )
                ->where('id', $sentId)
                ->value('sent_at'),
        );

        /*
         * Old pending notification
         * must be removed.
         */
        $this->assertDatabaseMissing(
            'vpn_telegram_usage_notifications',
            [
                'id' => $pendingId,
            ],
        );

        /*
         * New baseline must equal
         * consumption at reconnection.
         */
        $this->assertDatabaseHas(
            'vpn_telegram_usage_baselines',
            [
                'vpn_user_id' => $vpnUser->id,
                'usage_date' => $this->today(),
                'initial_total_bytes' =>
                    6 * self::GB,
            ],
        );

        /*
         * An old already-queued job
         * must not send anything.
         */
        Http::preventStrayRequests();
        Http::fake();

        $job = new SendVpnTelegramUsageNotification(
            $pendingId,
        );

        $job->handle(
            app(TelegramBotService::class),
            app(
                VpnTelegramUsageMessageFormatter::class,
            ),
        );

        Http::assertNothingSent();
    }

    public function test_first_link_at_four_gb_only_allows_future_five_gb_alert(): void
    {
        Bus::fake();

        $vpnUser = $this->createVpnUser();

        /*
         * User consumed 4 GB
         * before connecting Telegram.
         */
        $this->recordUsage(
            $vpnUser,
            4 * self::GB,
        );

        $token = $this->createLinkToken(
            $vpnUser,
        );

        $this->startBot(
            $token,
            333333,
        );

        $service = app(
            VpnTelegramUsageNotificationService::class,
        );

        /*
         * No historical 3 GB message.
         */
        $this->assertSame(
            0,
            $service->detect(),
        );

        $this->assertDatabaseCount(
            'vpn_telegram_usage_notifications',
            0,
        );

        /*
         * Usage later reaches 5 GB.
         */
        DB::table(
            'vpn_user_daily_usages',
        )
            ->where(
                'vpn_user_id',
                $vpnUser->id,
            )
            ->where(
                'usage_date',
                $this->today(),
            )
            ->update([
                'downlink_bytes' => 5 * self::GB,
                'updated_at' => now(),
            ]);

        $this->assertSame(
            1,
            $service->detect(),
        );

        $this->assertDatabaseMissing(
            'vpn_telegram_usage_notifications',
            [
                'vpn_user_id' => $vpnUser->id,
                'threshold_gb' => 3,
            ],
        );

        $this->assertDatabaseHas(
            'vpn_telegram_usage_notifications',
            [
                'vpn_user_id' => $vpnUser->id,
                'telegram_chat_id' => 333333,
                'threshold_gb' => 5,
            ],
        );

        Bus::assertDispatched(
            SendVpnTelegramUsageNotification::class,
            1,
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

    private function recordUsage(
        VpnUser $vpnUser,
        int $bytes,
    ): void {
        DB::table(
            'vpn_user_daily_usages',
        )->insert([
            'vpn_user_id' => $vpnUser->id,
            'usage_date' => $this->today(),
            'downlink_bytes' => $bytes,
            'uplink_bytes' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createNotification(
        VpnUser $vpnUser,
        int $chatId,
        int $threshold,
        bool $sent,
    ): int {
        return (int) DB::table(
            'vpn_telegram_usage_notifications',
        )->insertGetId([
            'vpn_user_id' => $vpnUser->id,
            'telegram_chat_id' => $chatId,
            'usage_date' => $this->today(),
            'threshold_gb' => $threshold,
            'total_bytes' => $threshold * self::GB,
            'sent_at' => $sent ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createLinkToken(
        VpnUser $vpnUser,
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
            'created_by' => null,
            'expires_at' => now()->addMinutes(30),
            'used_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $token;
    }

    private function startBot(
        string $token,
        int $chatId,
    ): void {
        $this->postJson(
            '/api/telegram/webhook',
            [
                'update_id' => 10001,
                'message' => [
                    'message_id' => 1,
                    'chat' => [
                        'id' => $chatId,
                        'type' => 'private',
                    ],
                    'from' => [
                        'id' => $chatId,
                        'is_bot' => false,
                        'first_name' => 'Abbas',
                        'username' => 'konjtester',
                    ],
                    'text' => '/start ' . $token,
                ],
            ],
            [
                'X-Telegram-Bot-Api-Secret-Token'
                => 'test-webhook-secret',
            ],
        )->assertOk();
    }

    private function today(): string
    {
        return CarbonImmutable::today('UTC')
            ->toDateString();
    }
}
