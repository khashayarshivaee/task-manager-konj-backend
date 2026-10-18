<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\TelegramBotService;
use App\Services\VpnTelegramUsageMessageFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

final class SendVpnTelegramUsageNotification implements
    ShouldQueue,
    ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 45;

    public int $uniqueFor = 600;

    public function __construct(
        public readonly int $notificationId,
    ) {
    }

    public function uniqueId(): string
    {
        return 'vpn-telegram-usage-' .
            $this->notificationId;
    }

    public function backoff(): array
    {
        return [60, 180, 300];
    }

    public function handle(
        TelegramBotService $telegram,
        VpnTelegramUsageMessageFormatter $formatter,
    ): void {
        /*
         * Read the pending notification.
         */
        $notification = DB::table(
            'vpn_telegram_usage_notifications',
        )
            ->where('id', $this->notificationId)
            ->first();

        if (
            $notification === null ||
            $notification->sent_at !== null
        ) {
            return;
        }

        /*
         * Do not send outdated daily
         * usage messages.
         */
        $today = CarbonImmutable::today('UTC')
            ->toDateString();

        if (
            (string) $notification->usage_date
            !== $today
        ) {
            return;
        }

        $thresholdGb =
            (int) $notification->threshold_gb;

        if (
            !in_array(
                $thresholdGb,
                [3, 5],
                true,
            )
        ) {
            return;
        }

        $vpnUserId =
            (int) $notification->vpn_user_id;

        $chatId =
            (int) $notification->telegram_chat_id;

        /*
         * Ensure that the VPN account
         * still exists and is active.
         */
        $vpnUser = DB::table('vpn_users')
            ->where('id', $vpnUserId)
            ->where('is_active', true)
            ->first([
                'id',
                'name',
            ]);

        if ($vpnUser === null) {
            return;
        }

        /*
         * Check the current Telegram
         * connection immediately before
         * sending the message.
         *
         * This prevents sending a
         * message to an old chat after
         * an account is disconnected
         * or linked to another account.
         */
        $isConnected = DB::table(
            'vpn_telegram_connections',
        )
            ->where(
                'vpn_user_id',
                $vpnUserId,
            )
            ->where(
                'telegram_chat_id',
                $chatId,
            )
            ->where(
                'is_active',
                true,
            )
            ->exists();

        if (!$isConnected) {
            return;
        }

        /*
         * Generate the approved
         * KONJ TEAM message.
         */
        $message = $formatter->format(
            (string) $vpnUser->name,
            $thresholdGb,
            (int) $notification->total_bytes,
        );

        /*
         * Send the Telegram message.
         *
         * If Telegram fails, the service
         * throws an exception and Laravel
         * retries the queued job.
         */
        $telegram->sendMessage(
            $chatId,
            $message,
        );

        /*
         * Mark as sent only after
         * Telegram accepts the message.
         */
        DB::table(
            'vpn_telegram_usage_notifications',
        )
            ->where(
                'id',
                $this->notificationId,
            )
            ->whereNull('sent_at')
            ->update([
                'sent_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
