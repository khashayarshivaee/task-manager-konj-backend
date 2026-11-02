<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MonitoredServer;
use App\Services\MonitoredServerHealthService;
use Illuminate\Http\JsonResponse;

final class MonitoredServerHealthController extends Controller
{
    public function __invoke(
        MonitoredServer $server,
        MonitoredServerHealthService $healthService,
    ): JsonResponse {

        // Disabled servers must not be accessible.
        abort_unless($server->is_enabled, 404);

        $health = $healthService->getLatest($server);

        $serverInfo = [
            'id' => $server->id,
            'slug' => $server->slug,
            'name' => $server->name,
            'region' => $server->region,
        ];

        if ($health === null) {
            return response()->json([
                'status' => 'pending',
                'server' => $serverInfo,
                'data' => null,
                'message' =>
                    'Server health data is not available yet.',
            ], 200, [
                'Cache-Control' => 'private, no-store',
            ]);
        }

        return response()->json([
            'status' => 'completed',
            'server' => $serverInfo,
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
