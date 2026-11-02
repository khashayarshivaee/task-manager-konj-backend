<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MonitoredServer;
use App\Services\MonitoredServerServicesStatusService;
use Illuminate\Http\JsonResponse;

final class MonitoredServerServicesStatusController extends Controller
{
    public function __invoke(
        MonitoredServer $server,
        MonitoredServerServicesStatusService $statusService,
    ): JsonResponse {
        // Disabled servers are not accessible.
        abort_unless($server->is_enabled, 404);

        $snapshot = $statusService->getLatest($server);

        $serverInfo = [
            'id' => $server->id,
            'slug' => $server->slug,
            'name' => $server->name,
            'region' => $server->region,
        ];

        if ($snapshot === null) {
            return response()->json([
                'status' => 'pending',
                'server' => $serverInfo,
                'data' => null,
                'message' =>
                    'Server services data is not available yet.',
            ], 200, [
                'Cache-Control' => 'private, no-store',
            ]);
        }

        $services = [];

        foreach ($snapshot['services'] as $service) {
            if (!is_array($service)) {
                continue;
            }

            $status = $service['status'] ?? 'unknown';

            if (!in_array(
                $status,
                ['active', 'down', 'unknown'],
                true,
            )) {
                $status = 'unknown';
            }

            $services[] = [
                'key' => (string) ($service['key'] ?? ''),
                'name' => (string) ($service['name'] ?? ''),
                'status' => $status,

                'uptime_seconds' =>
                    isset($service['uptime_seconds'])
                    && is_numeric($service['uptime_seconds'])
                        ? max(
                        0,
                        (int) $service['uptime_seconds'],
                    )
                        : null,
            ];
        }

        return response()->json([
            'status' => 'completed',
            'server' => $serverInfo,
            'data' => [
                'services' => $services,
                'checked_at' =>
                    $snapshot['checked_at'] ?? null,
            ],
        ], 200, [
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
