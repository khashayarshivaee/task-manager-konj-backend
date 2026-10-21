<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

final class ServerSpeedTestController extends Controller
{
    /**
     * Return the latest shared server speed test.
     *
     * Reading this endpoint does not trigger
     * a new speed test.
     */
    public function __invoke(): JsonResponse
    {
        $measurement = Cache::get(
            'server-status:speed-test:latest',
        );

        if (!is_array($measurement)) {
            return response()->json([
                'data' => null,
                'status' => 'pending',
                'message' =>
                    'No server speed test result is available yet.',
            ]);
        }

        return response()->json([
            'data' => [
                'download_mbps' =>
                    $measurement['download_mbps'] ?? null,

                'upload_mbps' =>
                    $measurement['upload_mbps'] ?? null,

                'ping_ms' =>
                    $measurement['ping_ms'] ?? null,

                'jitter_ms' =>
                    $measurement['jitter_ms'] ?? null,

                'server_name' =>
                    $measurement['server_name'] ?? null,

                'measured_at' =>
                    $measurement['measured_at'] ?? null,
            ],

            'status' => 'completed',
        ], 200, [
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
