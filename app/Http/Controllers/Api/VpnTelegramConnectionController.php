<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VpnUser;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class VpnTelegramConnectionController extends Controller
{
    /**
     * Get Telegram connection status
     * for a single VPN user.
     */
    public function show(
        VpnUser $vpnUser,
    ): JsonResponse {
        $connection = DB::table(
            'vpn_telegram_connections',
        )
            ->where(
                'vpn_user_id',
                $vpnUser->id,
            )
            ->first();

        $connected =
            $connection !== null
            && (bool) $connection->is_active;

        return response()->json([
            'data' => [
                'vpn_user_id' => $vpnUser->id,
                'vpn_user_name' => $vpnUser->name,

                'telegram' => [
                    'connected' => $connected,

                    'username' => $connected
                        ? $connection->telegram_username
                        : null,

                    'connected_at' => $connected
                        ? CarbonImmutable::parse(
                            $connection->connected_at,
                            'UTC',
                        )->toIso8601String()
                        : null,
                ],
            ],
        ]);
    }

    /**
     * Disconnect Telegram without
     * disabling the actual VPN user.
     */
    public function destroy(
        VpnUser $vpnUser,
    ): JsonResponse {
        $now = CarbonImmutable::now('UTC');

        DB::transaction(
            function () use (
                $vpnUser,
                $now,
            ): void {
                /*
                 * Serialize management operations
                 * for this VPN user.
                 */
                DB::table('vpn_users')
                    ->where('id', $vpnUser->id)
                    ->lockForUpdate()
                    ->first();

                /*
                 * Disable Telegram messages.
                 * Keep the record for history.
                 */
                DB::table(
                    'vpn_telegram_connections',
                )
                    ->where(
                        'vpn_user_id',
                        $vpnUser->id,
                    )
                    ->update([
                        'is_active' => false,
                        'updated_at' => $now,
                    ]);

                /*
                 * Invalidate outstanding
                 * invitation links.
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
            },
        );

        return response()->json([
            'message' =>
                'Telegram connection disconnected.',

            'data' => [
                'vpn_user_id' => $vpnUser->id,

                'telegram' => [
                    'connected' => false,
                ],
            ],
        ]);
    }
}
