<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('services.telegram.webhook_secret');
        $providedSecret = $request->header(
            'X-Telegram-Bot-Api-Secret-Token',
            '',
        );

        if (
            !is_string($secret) ||
            $secret === '' ||
            !is_string($providedSecret) ||
            !hash_equals($secret, $providedSecret)
        ) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $message = $request->input('message');

        if (!is_array($message)) {
            return $this->acknowledge();
        }

        $chatType = data_get($message, 'chat.type');
        $chatId = data_get($message, 'chat.id');
        $telegramUserId = data_get($message, 'from.id');

        // Telegram private chats have the same chat and user IDs.
        if (
            $chatType !== 'private' ||
            !is_int($chatId) ||
            !is_int($telegramUserId) ||
            $chatId <= 0 ||
            $chatId !== $telegramUserId
        ) {
            return $this->acknowledge();
        }

        $text = data_get($message, 'text');

        if (!is_string($text)) {
            return $this->acknowledge();
        }

        $text = trim($text);

        if ($text === '/start') {
            return $this->reply(
                $chatId,
                "Welcome to KONJ TEAM! 👋🧡\n\n"
                . "To activate personal VPN usage updates, "
                . "open the invitation link provided by your team administrator.",
            );
        }

        if (
            preg_match(
                '~^/start ([A-Za-z0-9_-]{32,64})$~',
                $text,
                $matches,
            ) !== 1
        ) {
            return $this->acknowledge();
        }

        $tokenHash = hash('sha256', $matches[1]);
        $telegramUsername = data_get($message, 'from.username');

        if (!is_string($telegramUsername)) {
            $telegramUsername = null;
        }

        try {
            $result = DB::transaction(
                function () use (
                    $tokenHash,
                    $chatId,
                    $telegramUserId,
                    $telegramUsername,
                ): array {
                    $now = CarbonImmutable::now('UTC');

                    // Invitations are single-use; serialize concurrent attempts.
                    $invitation = DB::table('vpn_telegram_link_tokens')
                        ->where('token_hash', $tokenHash)
                        ->whereNull('used_at')
                        ->where('expires_at', '>', $now)
                        ->lockForUpdate()
                        ->first();

                    if ($invitation === null) {
                        return ['status' => 'invalid'];
                    }

                    $vpnUser = DB::table('vpn_users')
                        ->where('id', $invitation->vpn_user_id)
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->first(['id', 'name']);

                    if ($vpnUser === null) {
                        return ['status' => 'invalid'];
                    }

                    // Never let an active Telegram chat belong to two VPN users.
                    $chatConnection = DB::table('vpn_telegram_connections')
                        ->where('telegram_chat_id', $chatId)
                        ->lockForUpdate()
                        ->first();

                    if (
                        $chatConnection !== null &&
                        (int) $chatConnection->vpn_user_id !== (int) $vpnUser->id
                    ) {
                        if ((bool) $chatConnection->is_active) {
                            // No token, connection, or baseline is changed.
                            return ['status' => 'already_connected'];
                        }

                        // A disconnected chat can move to a new VPN user.
                        DB::table('vpn_telegram_connections')
                            ->where('id', $chatConnection->id)
                            ->where('is_active', false)
                            ->delete();
                    }

                    $targetConnection = DB::table('vpn_telegram_connections')
                        ->where('vpn_user_id', $vpnUser->id)
                        ->lockForUpdate()
                        ->first(['id']);

                    $connectionData = [
                        'telegram_chat_id' => $chatId,
                        'telegram_user_id' => $telegramUserId,
                        'telegram_username' => $telegramUsername,
                        'is_active' => true,
                        'connected_at' => $now,
                        'updated_at' => $now,
                    ];

                    // Do not use upsert here: MySQL considers *all* unique
                    // indexes as possible conflict targets, not just vpn_user_id.
                    if ($targetConnection === null) {
                        DB::table('vpn_telegram_connections')->insert([
                            'vpn_user_id' => (int) $vpnUser->id,
                            ...$connectionData,
                            'created_at' => $now,
                        ]);
                    } else {
                        DB::table('vpn_telegram_connections')
                            ->where('id', $targetConnection->id)
                            ->update($connectionData);
                    }

                    $today = $now->toDateString();
                    $usage = DB::table('vpn_user_daily_usages')
                        ->where('vpn_user_id', $vpnUser->id)
                        ->where('usage_date', $today)
                        ->first(['downlink_bytes', 'uplink_bytes']);

                    $initialTotalBytes = $usage === null
                        ? 0
                        : (int) $usage->downlink_bytes
                        + (int) $usage->uplink_bytes;

                    // Skip thresholds crossed before this link was activated.
                    DB::table('vpn_telegram_usage_baselines')->upsert(
                        [[
                            'vpn_user_id' => (int) $vpnUser->id,
                            'usage_date' => $today,
                            'initial_total_bytes' => $initialTotalBytes,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]],
                        ['vpn_user_id', 'usage_date'],
                        ['initial_total_bytes', 'updated_at'],
                    );

                    // Old queued jobs will find no pending record and exit.
                    // Previously sent notifications remain for deduplication.
                    DB::table('vpn_telegram_usage_notifications')
                        ->where('vpn_user_id', $vpnUser->id)
                        ->where('usage_date', $today)
                        ->whereNull('sent_at')
                        ->delete();

                    // Consume the invite only after every change succeeds.
                    DB::table('vpn_telegram_link_tokens')
                        ->where('id', $invitation->id)
                        ->update([
                            'used_at' => $now,
                            'updated_at' => $now,
                        ]);

                    return [
                        'status' => 'connected',
                        'vpn_user_name' => (string) $vpnUser->name,
                    ];
                },
                3,
            );
        } catch (UniqueConstraintViolationException) {
            // A competing request may have claimed the chat or VPN first.
            // The transaction rolls back, leaving the invite usable.
            $result = ['status' => 'already_connected'];
        }

        if ($result['status'] === 'invalid') {
            return $this->reply(
                $chatId,
                "This invitation link is invalid, expired, or already used. 🔗\n\n"
                . "Please request a new link from your KONJ administrator. 🧡",
            );
        }

        if ($result['status'] === 'already_connected') {
            return $this->reply(
                $chatId,
                "This Telegram account is already linked to another VPN profile. 🔒\n\n"
                . "Please ask your KONJ administrator to disconnect it before trying again. 🧡",
            );
        }

        $vpnUserName = $result['vpn_user_name'];

        return $this->reply(
            $chatId,
            "You're connected to KONJ TEAM! 🎉\n\n"
            . "Hi {$vpnUserName}, your personal VPN usage updates are now active.\n\n"
            . "We'll send a friendly update at 3 GB and 5 GB of daily usage. "
            . "No action needed. 🧡",
        );
    }

    private function acknowledge(): JsonResponse
    {
        return response()->json(['ok' => true]);
    }

    private function reply(int $chatId, string $text): JsonResponse
    {
        return response()->json([
            'method' => 'sendMessage',
            'chat_id' => $chatId,
            'text' => $text,
        ]);
    }
}
