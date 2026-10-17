<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VpnUser;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VpnDailyUsageController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => [
                'sometimes',
                'string',
                'in:today,yesterday,last7days',
            ],
        ]);

        $period = $validated['period'] ?? 'today';

        $today = CarbonImmutable::today('UTC');

        $from = match ($period) {
            'yesterday' => $today->subDay(),
            'last7days' => $today->subDays(6),
            default => $today,
        };

        $to = $period === 'yesterday'
            ? $today->subDay()
            : $today;

        $users = VpnUser::query()
            ->active()
            ->orderBy('id')
            ->get(['id', 'name']);

        $records = DB::table('vpn_user_daily_usages')
            ->whereIn(
                'vpn_user_id',
                $users->pluck('id')->all(),
            )
            ->whereBetween('usage_date', [
                $from->toDateString(),
                $to->toDateString(),
            ])
            ->get([
                'vpn_user_id',
                'usage_date',
                'downlink_bytes',
                'uplink_bytes',
            ])
            ->groupBy('vpn_user_id');

        $data = $users->map(
            function (VpnUser $user) use (
                $records,
                $from,
                $to,
            ): array {
                $userRecords = $records
                    ->get($user->id, collect())
                    ->keyBy('usage_date');

                $days = [];
                $totalDownlink = 0;
                $totalUplink = 0;

                for (
                    $date = $from;
                    $date->lessThanOrEqualTo($to);
                    $date = $date->addDay()
                ) {
                    $dateString = $date->toDateString();
                    $record = $userRecords->get($dateString);

                    $downlink = (int) (
                        $record?->downlink_bytes ?? 0
                    );

                    $uplink = (int) (
                        $record?->uplink_bytes ?? 0
                    );

                    $totalDownlink += $downlink;
                    $totalUplink += $uplink;

                    $days[] = [
                        'date' => $dateString,
                        'downlink_bytes' => $downlink,
                        'uplink_bytes' => $uplink,
                        'total_bytes' => $downlink + $uplink,
                    ];
                }

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'downlink_bytes' => $totalDownlink,
                    'uplink_bytes' => $totalUplink,
                    'total_bytes' =>
                        $totalDownlink + $totalUplink,
                    'days' => $days,
                ];
            },
        )->values();

        return response()->json([
            'data' => [
                'period' => $period,
                'timezone' => 'UTC',
                'from_date' => $from->toDateString(),
                'to_date' => $to->toDateString(),
                'users' => $data,
            ],
        ]);
    }
}
