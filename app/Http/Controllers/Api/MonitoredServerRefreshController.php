<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MonitoredServer;
use App\Services\MonitoredServerHealthService;
use App\Services\MonitoredServerServicesStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

final class MonitoredServerRefreshController extends Controller
{
    public function __invoke(
        MonitoredServer $server,
        MonitoredServerHealthService $healthService,
        MonitoredServerServicesStatusService $servicesService,
    ): JsonResponse {
        abort_unless(
            $server->is_enabled &&
            $server->driver === 'ssh',
            404,
        );

        if (!config(
            "server_monitoring.remotes.{$server->slug}.enabled",
            false,
        )) {
            return response()->json([
                'status' => 'error',
                'message' => 'Remote monitoring is disabled.',
            ], 503);
        }

        // Prevent simultaneous SSH collections.
        $lock = Cache::lock(
            "server-status:servers:{$server->slug}:refresh",
            30,
        );

        if (!$lock->get()) {
            return response()->json([
                'status' => 'busy',
                'message' => 'Refresh is already in progress.',
            ], 409);
        }

        try {
            // Reuse the existing, tested collector.
            $exitCode = Artisan::call(
                'server-status:collect-remote',
                ['server' => $server->slug],
            );

            if ($exitCode !== 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unable to refresh server monitoring.',
                ], 503);
            }

            $health = $healthService->getLatest($server);
            $services = $servicesService->getLatest($server);

            if ($health === null || $services === null) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Fresh monitoring data is unavailable.',
                ], 503);
            }

            return response()->json([
                'status' => 'completed',
                'server' => [
                    'id' => $server->id,
                    'slug' => $server->slug,
                    'name' => $server->name,
                    'region' => $server->region,
                ],
                'health' => $health,
                'services' => $services,
            ], 200, [
                'Cache-Control' => 'private, no-store',
            ]);

        } finally {
            $lock->release();
        }
    }
}
