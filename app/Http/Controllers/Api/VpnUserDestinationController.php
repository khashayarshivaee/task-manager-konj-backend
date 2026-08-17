<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VpnUser;
use Illuminate\Http\JsonResponse;

class VpnUserDestinationController extends Controller
{
    public function index(
        VpnUser $vpnUser,
    ): JsonResponse {
        $destinations = $vpnUser
            ->destinations()
            ->orderByDesc('last_seen_at')
            ->get([
                'id',
                'destination',
                'connection_count',
                'total_duration_seconds',
                'first_seen_at',
                'last_seen_at',
                'uplink_bytes',
                'downlink_bytes',
                'total_bytes',
            ]);

        return response()->json([
            'data' => $destinations,
        ]);
    }
}
