<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ClientSpeedTestController extends Controller
{
    private const MAX_UPLOAD_BYTES = 1024 * 1024;

    /**
     * Measure client-to-server response latency.
     */
    public function ping(): JsonResponse
    {
        return response()->json(
            [
                'data' => [
                    'status' => 'ok',
                ],
            ],
            200,
            [
                'Cache-Control' => 'no-store',
            ],
        );
    }

    /**
     * Download a configurable amount of random data.
     *
     * Supported sizes:
     * 2 MiB - Initial measurement / warm-up
     * 4 MiB - Medium connection
     * 8 MiB - Faster connection
     *
     * Maximum: 8 MiB per request.
     */
    public function download(
        Request $request,
    ): Response|JsonResponse {

        $megabytes = $request->query(
            'mb',
            '2',
        );

        if (
            !is_string($megabytes) ||
            !in_array(
                $megabytes,
                ['2', '4', '8'],
                true,
            )
        ) {
            return response()->json(
                [
                    'message' =>
                        'Invalid download size.',
                ],
                422,
            );
        }

        $downloadBytes =
            (int) $megabytes * 1024 * 1024;

        $payload = random_bytes(
            $downloadBytes,
        );

        return response(
            $payload,
            200,
            [
                'Content-Type' =>
                    'application/octet-stream',

                'Content-Length' =>
                    (string) $downloadBytes,

                'Cache-Control' =>
                    'private, no-store, no-transform',

                'X-Content-Type-Options' =>
                    'nosniff',
            ],
        );
    }

    /**
     * Receive up to 1 MiB per upload request.
     */
    public function upload(
        Request $request,
    ): JsonResponse {

        $declaredLength = (int) $request->header(
            'Content-Length',
            '0',
        );

        if (
            $declaredLength >
            self::MAX_UPLOAD_BYTES
        ) {
            return response()->json(
                [
                    'message' =>
                        'Upload payload is too large.',
                ],
                413,
            );
        }

        $receivedBytes = strlen(
            $request->getContent(),
        );

        if (
            $receivedBytes >
            self::MAX_UPLOAD_BYTES
        ) {
            return response()->json(
                [
                    'message' =>
                        'Upload payload is too large.',
                ],
                413,
            );
        }

        if ($receivedBytes === 0) {
            return response()->json(
                [
                    'message' =>
                        'Upload payload is empty.',
                ],
                422,
            );
        }

        return response()->json(
            [
                'data' => [
                    'received_bytes' =>
                        $receivedBytes,
                ],
            ],
            200,
            [
                'Cache-Control' => 'no-store',
            ],
        );
    }
}
