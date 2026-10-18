<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VpnUser;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class VpnTelegramLinkController extends Controller
{
    public function __invoke(
        Request $request,
        VpnUser $vpnUser,
    ): JsonResponse {
        /*
         * Only active VPN users can receive
         * a Telegram invitation.
         */
        if (!$vpnUser->is_active) {
            return response()->json([
                'message' => 'VPN user is inactive.',
            ], 409);
        }

        /*
         * Bot configuration.
         */
        $botUsername = trim(
            (string) config(
                'services.telegram.bot_username',
                '',
            ),
        );

        if (
            preg_match(
                '/^[A-Za-z0-9_]{5,32}$/D',
                $botUsername,
            ) !== 1 ||
            !str_ends_with(
                strtolower($botUsername),
                'bot',
            )
        ) {
            return response()->json([
                'message' =>
                    'Telegram bot is not configured.',
            ], 503);
        }

        /*
         * Generate a cryptographically random
         * invitation token.
         *
         * Store only the SHA-256 hash.
         */
        $token = Str::random(48);

        $tokenHash = hash(
            'sha256',
            $token,
        );

        $now = CarbonImmutable::now('UTC');

        $expiresAt = $now->addMinutes(30);

        DB::transaction(function () use (
            $request,
            $vpnUser,
            $tokenHash,
            $now,
            $expiresAt,
        ): void {
            /*
             * Lock this VPN user so concurrent
             * requests cannot create multiple
             * active invitation links.
             */
            $lockedUser = DB::table('vpn_users')
                ->where('id', $vpnUser->id)
                ->lockForUpdate()
                ->first([
                    'id',
                    'is_active',
                ]);

            abort_if(
                $lockedUser === null,
                404,
            );

            abort_unless(
                (bool) $lockedUser->is_active,
                409,
                'VPN user is inactive.',
            );

            /*
             * Invalidate previously generated,
             * unused invitation links.
             */
            DB::table(
                'vpn_telegram_link_tokens',
            )
                ->where(
                    'vpn_user_id',
                    $vpnUser->id,
                )
                ->whereNull('used_at')
                ->where('expires_at', '>', $now)
                ->update([
                    'expires_at' => $now,
                    'updated_at' => $now,
                ]);

            /*
             * Save the new invitation.
             */
            DB::table(
                'vpn_telegram_link_tokens',
            )->insert([
                'vpn_user_id' => $vpnUser->id,

                'token_hash' => $tokenHash,

                'created_by' =>
                    $request->user()->id,

                'expires_at' => $expiresAt,

                'used_at' => null,

                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        /*
         * The raw token is returned once.
         * It is never saved in the database.
         */
        $link = sprintf(
            'https://t.me/%s?start=%s',
            $botUsername,
            $token,
        );

        return response()->json([
            'data' => [
                'vpn_user_id' => $vpnUser->id,

                'vpn_user_name' => $vpnUser->name,

                'bot_username' => $botUsername,

                'link' => $link,

                'expires_at' =>
                    $expiresAt->toIso8601String(),
            ],
        ], 201);
    }
}
