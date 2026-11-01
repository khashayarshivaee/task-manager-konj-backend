<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\VpnUser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class VpnServerActivityService
{
    private const RECENT_MINUTES = 3;

    public function snapshot(): array
    {
        $now = CarbonImmutable::now('UTC');

        $users = VpnUser::query()
            ->orderBy('id')
            ->get(['id', 'name', 'is_active']);

        $userIds = $users->pluck('id')->all();

        $todayUsage = DB::table('vpn_user_daily_usages')
            ->whereIn('vpn_user_id', $userIds)
            ->where('usage_date', $now->toDateString())
            ->get([
                'vpn_user_id',
                'uplink_bytes',
                'downlink_bytes',
            ])
            ->keyBy('vpn_user_id');

        $lastUsage = DB::table('vpn_user_daily_usages')
            ->whereIn('vpn_user_id', $userIds)
            ->select('vpn_user_id')
            ->selectRaw(
                'MAX(updated_at) AS last_usage_recorded_at'
            )
            ->groupBy('vpn_user_id')
            ->pluck(
                'last_usage_recorded_at',
                'vpn_user_id'
            );

        $rows = $users->map(
            function (VpnUser $user) use (
                $todayUsage,
                $lastUsage,
                $now,
            ): array {
                $today = $todayUsage->get($user->id);

                $upload = (int) (
                    $today?->uplink_bytes ?? 0
                );

                $download = (int) (
                    $today?->downlink_bytes ?? 0
                );

                $lastValue = $lastUsage->get($user->id);

                $lastRecordedAt = $lastValue
                    ? CarbonImmutable::parse(
                        (string) $lastValue,
                        'UTC',
                    )
                    : null;

                $hasRecentTraffic = (
                    $user->is_active &&
                    $lastRecordedAt !== null &&
                    $lastRecordedAt->greaterThanOrEqualTo(
                        $now->subMinutes(
                            self::RECENT_MINUTES
                        )
                    )
                );

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'is_active' => $user->is_active,

                    // This is not a live connection count.
                    'has_recent_traffic' => $hasRecentTraffic,

                    'last_usage_recorded_at' =>
                        $lastRecordedAt?->toIso8601String(),

                    'today' => [
                        'uplink_bytes' => $upload,
                        'downlink_bytes' => $download,
                        'total_bytes' => $upload + $download,
                    ],
                ];
            },
        )->values();

        return [
            'generated_at' => $now->toIso8601String(),
            'server' => (string) config('xray.server'),
            'recent_window_minutes' =>
                self::RECENT_MINUTES,

            'summary' => [
                'total_users' => $rows->count(),

                'enabled_users' => $rows
                    ->where('is_active', true)
                    ->count(),

                'recent_traffic_users' => $rows
                    ->where('has_recent_traffic', true)
                    ->count(),

                'today_uplink_bytes' => $rows
                    ->sum('today.uplink_bytes'),

                'today_downlink_bytes' => $rows
                    ->sum('today.downlink_bytes'),
            ],

            'users' => $rows->all(),
        ];
    }
}
