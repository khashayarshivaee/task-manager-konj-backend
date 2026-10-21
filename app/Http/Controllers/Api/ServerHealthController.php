<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

final class ServerHealthController extends Controller
{
    /**
     * Return the latest server health snapshot.
     *
     * Reading this endpoint does not
     * trigger a new server measurement.
     */
    public function __invoke(): JsonResponse
    {
        $health = Cache::get(
            'server-status:health:latest',
        );

        if (!is_array($health)) {
            return response()->json([
                'status' => 'pending',

                'data' => null,

                'message' =>
                    'Server health data is not available yet.',
            ], 200, [
                'Cache-Control' => 'private, no-store',
            ]);
        }

        return response()->json([
            'status' => 'completed',

            'data' => [
                'cpu_percent' =>
                    $health['cpu_percent'] ?? null,

                'memory_total_bytes' =>
                    $health['memory_total_bytes'] ?? null,

                'memory_used_bytes' =>
                    $health['memory_used_bytes'] ?? null,

                'memory_percent' =>
                    $health['memory_percent'] ?? null,

                'disk_total_bytes' =>
                    $health['disk_total_bytes'] ?? null,

                'disk_used_bytes' =>
                    $health['disk_used_bytes'] ?? null,

                'disk_percent' =>
                    $health['disk_percent'] ?? null,

                'uptime_seconds' =>
                    $health['uptime_seconds'] ?? null,

                'measured_at' =>
                    $health['measured_at'] ?? null,
            ],
        ], 200, [
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
