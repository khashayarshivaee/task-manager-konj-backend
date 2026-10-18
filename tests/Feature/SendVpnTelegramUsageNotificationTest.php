<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendVpnTelegramUsageNotification;
use App\Models\VpnUser;
use App\Services\TelegramBotService;
use App\Services\VpnTelegramUsageMessageFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class SendVpnTelegramUsageNotificationTest
    extends TestCase
{
    use RefreshDatabase;

    private const GB = 1024 * 1024 * 1024;

    private const CHAT_ID = 123456;
    private bool $telegramShouldFail = false;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'services.telegram.bot_token',
            'TEST_BOT_TOKEN',
        );

        Http::preventStrayRequests();

        Http::fake([
            'https://api.telegram.org/*' => function () {
                if ($this->telegramShouldFail) {
                    return Http::response([
                        'ok' => false,
                        'description' => 'Test failure',
                    ], 500);
                }

                return Http::response([
                    'ok' => true,
                    'result' => [
                        'message_id' => 100,
                    ],
                ], 200);
            },
        ]);
    }

    public function test_three_gb_message_is_sent_successfully(): void
    {
        [$vpnUser, $notificationId] =
            $this->createNotification(
                'ABBAS & TEAM',
                3,
            );

        $this->runJob($notificationId);

        Http::assertSent(
            function ($request): bool {
                return
                    str_ends_with(
                        $request->url(),
                        '/sendMessage',
                    )
                    && $request['chat_id']
                    === self::CHAT_ID
                    && $request['parse_mode']
                    === 'HTML'
                    && str_contains(
                        $request['text'],
                        'ABBAS &amp; TEAM',
                    )
                    && str_contains(
                        $request['text'],
                        '3 GB',
                    );
            },
        );

        $this->assertNotNull(
            DB::table(
                'vpn_telegram_usage_notifications',
            )
                ->where('id', $notificationId)
                ->value('sent_at'),
        );
    }

    public function test_five_gb_message_uses_approved_text(): void
    {
        [, $notificationId] =
            $this->createNotification(
                'ABBAS',
                5,
            );

        $this->runJob($notificationId);

        Http::assertSent(
            function ($request): bool {
                return str_contains(
                        $request['text'],
                        'Your VPN usage has now reached',
                    )
                    && str_contains(
                        $request['text'],
                        '5 GB',
                    )
                    && str_contains(
                        $request['text'],
                        'No action needed',
                    );
            },
        );

        Http::assertSentCount(1);
    }

    public function test_sent_notification_is_not_sent_again(): void
    {
        [, $notificationId] =
            $this->createNotification();

        DB::table(
            'vpn_telegram_usage_notifications',
        )
            ->where('id', $notificationId)
            ->update([
                'sent_at' => now(),
            ]);

        $this->runJob($notificationId);

        Http::assertNothingSent();
    }

    public function test_disconnected_account_receives_nothing(): void
    {
        [$vpnUser, $notificationId] =
            $this->createNotification();

        DB::table(
            'vpn_telegram_connections',
        )
            ->where('vpn_user_id', $vpnUser->id)
            ->update([
                'is_active' => false,
            ]);

        $this->runJob($notificationId);

        Http::assertNothingSent();

        $this->assertPending($notificationId);
    }

    public function test_changed_telegram_chat_receives_nothing(): void
    {
        [$vpnUser, $notificationId] =
            $this->createNotification();

        /*
         * The account was reconnected
         * to a different Telegram chat.
         */
        DB::table(
            'vpn_telegram_connections',
        )
            ->where('vpn_user_id', $vpnUser->id)
            ->update([
                'telegram_chat_id' => 999999,
                'telegram_user_id' => 999999,
            ]);

        $this->runJob($notificationId);

        Http::assertNothingSent();

        $this->assertPending($notificationId);
    }

    public function test_inactive_vpn_user_receives_nothing(): void
    {
        [$vpnUser, $notificationId] =
            $this->createNotification();

        $vpnUser->update([
            'is_active' => false,
        ]);

        $this->runJob($notificationId);

        Http::assertNothingSent();

        $this->assertPending($notificationId);
    }

    public function test_yesterday_notification_is_not_sent(): void
    {
        [, $notificationId] =
            $this->createNotification();

        DB::table(
            'vpn_telegram_usage_notifications',
        )
            ->where('id', $notificationId)
            ->update([
                'usage_date' => now('UTC')
                    ->subDay()
                    ->toDateString(),
            ]);

        $this->runJob($notificationId);

        Http::assertNothingSent();

        $this->assertPending($notificationId);
    }

    public function test_failed_telegram_request_remains_pending(): void
    {
        [, $notificationId] =
            $this->createNotification();

        $this->telegramShouldFail = true;

        try {
            $this->runJob($notificationId);

            $this->fail(
                'Expected Telegram failure.',
            );
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'Telegram API request failed',
                $exception->getMessage(),
            );
        }

        Http::assertSentCount(1);

        $this->assertPending($notificationId);
    }

    /**
     * @return array{VpnUser, int}
     */
    private function createNotification(
        string $name = 'ABBAS',
        int $thresholdGb = 3,
    ): array {
        $vpnUser = VpnUser::query()->create([
            'name' => $name,
            'uuid' => (string) Str::uuid(),
            'xray_email' =>
                Str::uuid() . '@example.test',
            'is_active' => true,
            'flow' => 'xtls-rprx-vision',
        ]);

        DB::table(
            'vpn_telegram_connections',
        )->insert([
            'vpn_user_id' => $vpnUser->id,
            'telegram_chat_id' => self::CHAT_ID,
            'telegram_user_id' => self::CHAT_ID,
            'telegram_username' => 'konjtester',
            'is_active' => true,
            'connected_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $notificationId = DB::table(
            'vpn_telegram_usage_notifications',
        )->insertGetId([
            'vpn_user_id' => $vpnUser->id,
            'telegram_chat_id' => self::CHAT_ID,
            'usage_date' => now('UTC')
                ->toDateString(),
            'threshold_gb' => $thresholdGb,
            'total_bytes' =>
                $thresholdGb * self::GB,
            'sent_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            $vpnUser,
            (int) $notificationId,
        ];
    }

    private function runJob(
        int $notificationId,
    ): void {
        $job = new SendVpnTelegramUsageNotification(
            $notificationId,
        );

        $job->handle(
            app(TelegramBotService::class),
            app(
                VpnTelegramUsageMessageFormatter::class,
            ),
        );
    }

    private function assertPending(
        int $notificationId,
    ): void {
        $this->assertDatabaseHas(
            'vpn_telegram_usage_notifications',
            [
                'id' => $notificationId,
                'sent_at' => null,
            ],
        );
    }
}
