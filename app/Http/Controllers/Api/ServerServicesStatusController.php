<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

final class ServerServicesStatusController extends Controller
{
    /**
     * Return the latest cached services status.
     *
     * No system commands are executed
     * when users request this endpoint.
     */
    public function __invoke(): JsonResponse
    {
        $snapshot = Cache::get(
            'server-status:services:latest',
        );

        if (
            !is_array($snapshot) ||
            !isset($snapshot['services']) ||
            !is_array($snapshot['services'])
        ) {
            return response()->json([
                'status' => 'pending',
                'data' => null,
                'message' =>
                    'Service status data is not available yet.',
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
                'key' => (string) (
                    $service['key'] ?? ''
                ),

                'name' => (string) (
                    $service['name'] ?? ''
                ),

                'status' => $status,

                'uptime_seconds' =>
                    isset($service['uptime_seconds'])
                        ? (int) $service['uptime_seconds']
                        : null,
            ];
        }

        return response()->json([
            'status' => 'completed',

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
