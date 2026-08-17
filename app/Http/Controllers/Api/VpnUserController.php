<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VpnUser;
use App\Services\VpnConnectionService;
use App\Services\XrayManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class VpnUserController extends Controller
{
    public function index(
        XrayManagerService $xrayManagerService,
        VpnConnectionService $vpnConnectionService,
    ): JsonResponse {
        $vpnUsers = VpnUser::query()
            ->latest('id')
            ->get();

        $data = $vpnUsers
            ->map(
                function (VpnUser $vpnUser) use (
                    $xrayManagerService,
                    $vpnConnectionService,
                ): array {
                    try {
                        $stats = $xrayManagerService
                            ->getUserStats($vpnUser);

                        $statsAvailable = true;
                    } catch (RuntimeException $exception) {
                        report($exception);

                        $stats = [
                            'uplink_bytes' => 0,
                            'downlink_bytes' => 0,
                            'total_bytes' => 0,
                        ];

                        $statsAvailable = false;
                    }

                    try {
                        $onlineSessions = $xrayManagerService
                            ->getOnlineSessionCount(
                                $vpnUser,
                            );

                        $onlineAvailable = true;
                    } catch (RuntimeException $exception) {
                        report($exception);

                        $onlineSessions = 0;
                        $onlineAvailable = false;
                    }

                    try {
                        $connection = $vpnConnectionService
                            ->getConnectionData(
                                $vpnUser,
                            );

                        $connectionAvailable = true;
                    } catch (RuntimeException $exception) {
                        report($exception);

                        $connection = null;
                        $connectionAvailable = false;
                    }

                    return [
                        'id' => $vpnUser->id,
                        'name' => $vpnUser->name,
                        'uuid' => $vpnUser->uuid,
                        'xray_email' => $vpnUser->xray_email,

                        'is_active' => $vpnUser->is_active,
                        'flow' => $vpnUser->flow,

                        /*
                         * Live Xray status.
                         *
                         * Active means the VPN credential
                         * is allowed to connect.
                         *
                         * Online means at least one active
                         * Xray session currently exists.
                         */
                        'online_available' => $onlineAvailable,
                        'is_online' => $onlineSessions > 0,
                        'online_sessions' => $onlineSessions,

                        /*
                         * Traffic usage.
                         *
                         * Values are returned in bytes.
                         * Frontend converts them to
                         * KB / MB / GB / TB.
                         */
                        'stats_available' => $statsAvailable,
                        'stats' => [
                            'uplink_bytes' =>
                                $stats['uplink_bytes'],

                            'downlink_bytes' =>
                                $stats['downlink_bytes'],

                            'total_bytes' =>
                                $stats['total_bytes'],
                        ],

                        'connection_available' =>
                            $connectionAvailable,

                        'connection' =>
                            $connection,

                        'revoked_at' => $vpnUser
                            ->revoked_at
                            ?->toISOString(),

                        'created_at' => $vpnUser
                            ->created_at
                            ?->toISOString(),
                    ];
                },
            )
            ->values();

        return response()->json([
            'data' => $data,
        ]);
    }

    public function store(
        Request $request,
        XrayManagerService $xrayManagerService,
        VpnConnectionService $vpnConnectionService,
    ): JsonResponse {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
            ],
        ]);

        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $uuid = Str::uuid()->toString();

        $flow = (string) config(
            'xray.flow',
            'xtls-rprx-vision',
        );

        /*
         * Validate connection configuration before
         * creating the DB record or touching Xray.
         */
        $draftVpnUser = new VpnUser([
            'name' => $validated['name'],
            'uuid' => $uuid,
            'flow' => $flow,
        ]);

        try {
            $connection = $vpnConnectionService
                ->getConnectionData(
                    $draftVpnUser,
                );
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json(
                [
                    'message' =>
                        'VPN connection configuration is unavailable.',
                ],
                503,
            );
        }

        try {
            /** @var VpnUser $vpnUser */
            $vpnUser = DB::transaction(
                function () use (
                    $validated,
                    $user,
                    $uuid,
                    $flow,
                    $xrayManagerService,
                ): VpnUser {
                    $vpnUser = VpnUser::query()->create([
                        'name' =>
                            $validated['name'],

                        'uuid' =>
                            $uuid,

                        'xray_email' =>
                            sprintf(
                                'vpn-%s@task-manager.local',
                                $uuid,
                            ),

                        'is_active' =>
                            true,

                        'flow' =>
                            $flow,

                        'created_by' =>
                            $user->id,
                    ]);

                    $xrayManagerService->addUser(
                        $vpnUser,
                    );

                    return $vpnUser;
                },
            );
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json(
                [
                    'message' =>
                        'Unable to create VPN user.',
                ],
                503,
            );
        }

        return response()->json(
            [
                'data' => [
                    'id' =>
                        $vpnUser->id,

                    'name' =>
                        $vpnUser->name,

                    'uuid' =>
                        $vpnUser->uuid,

                    'xray_email' =>
                        $vpnUser->xray_email,

                    'is_active' =>
                        $vpnUser->is_active,

                    'is_online' =>
                        false,

                    'online_sessions' =>
                        0,

                    'flow' =>
                        $vpnUser->flow,

                    'stats' => [
                        'uplink_bytes' => 0,
                        'downlink_bytes' => 0,
                        'total_bytes' => 0,
                    ],

                    'created_at' =>
                        $vpnUser
                            ->created_at
                            ?->toISOString(),

                    'connection' =>
                        $connection,
                ],
            ],
            201,
        );
    }

    public function destroy(
        VpnUser $vpnUser,
        XrayManagerService $xrayManagerService,
    ): JsonResponse {
        if ($vpnUser->is_active) {
            try {
                $xrayManagerService->removeUser(
                    $vpnUser,
                );
            } catch (RuntimeException $exception) {
                report($exception);

                return response()->json(
                    [
                        'message' =>
                            'Unable to revoke VPN user.',
                    ],
                    503,
                );
            }

            $vpnUser->forceFill([
                'is_active' => false,
                'revoked_at' => now(),
            ])->save();
        }

        return response()->json([
            'data' => [
                'id' =>
                    $vpnUser->id,

                'name' =>
                    $vpnUser->name,

                'is_active' =>
                    $vpnUser->is_active,

                'is_online' =>
                    false,

                'online_sessions' =>
                    0,

                'revoked_at' =>
                    $vpnUser
                        ->revoked_at
                        ?->toISOString(),
            ],
        ]);
    }

    public function enable(
        VpnUser $vpnUser,
        XrayManagerService $xrayManagerService,
    ): JsonResponse {
        if (! $vpnUser->is_active) {
            try {
                $xrayManagerService->addUser(
                    $vpnUser,
                );
            } catch (RuntimeException $exception) {
                report($exception);

                return response()->json(
                    [
                        'message' =>
                            'Unable to enable VPN user.',
                    ],
                    503,
                );
            }

            $vpnUser->forceFill([
                'is_active' => true,
                'revoked_at' => null,
            ])->save();
        }

        return response()->json([
            'data' => [
                'id' =>
                    $vpnUser->id,

                'name' =>
                    $vpnUser->name,

                'is_active' =>
                    $vpnUser->is_active,

                'is_online' =>
                    false,

                'online_sessions' =>
                    0,

                'revoked_at' =>
                    $vpnUser
                        ->revoked_at
                        ?->toISOString(),
            ],
        ]);
    }
}
