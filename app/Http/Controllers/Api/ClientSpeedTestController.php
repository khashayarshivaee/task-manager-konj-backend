<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ClientSpeedTestController extends Controller
{
    private const DOWNLOAD_BYTES = 2 * 1024 * 1024;

    private const MAX_UPLOAD_BYTES = 1024 * 1024;

    /**
     * Lightweight endpoint for latency measurement.
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
     * Send 2 MiB of random data to measure download.
     */
    public function download(): Response
    {
        $payload = random_bytes(
            self::DOWNLOAD_BYTES,
        );

        return response(
            $payload,
            200,
            [
                'Content-Type' => 'application/octet-stream',
                'Content-Length' => (string) self::DOWNLOAD_BYTES,
                'Cache-Control' => 'private, no-store, no-transform',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    /**
     * Receive up to 1 MiB to measure upload.
     */
    public function upload(Request $request): JsonResponse
    {
        $declaredLength = (int) $request->header(
            'Content-Length',
            '0',
        );

        if ($declaredLength > self::MAX_UPLOAD_BYTES) {
            return response()->json(
                [
                    'message' => 'Upload payload is too large.',
                ],
                413,
            );
        }

        $receivedBytes = strlen(
            $request->getContent(),
        );

        if ($receivedBytes > self::MAX_UPLOAD_BYTES) {
            return response()->json(
                [
                    'message' => 'Upload payload is too large.',
                ],
                413,
            );
        }

        if ($receivedBytes === 0) {
            return response()->json(
                [
                    'message' => 'Upload payload is empty.',
                ],
                422,
            );
        }

        return response()->json(
            [
                'data' => [
                    'received_bytes' => $receivedBytes,
                ],
            ],
            200,
            [
                'Cache-Control' => 'no-store',
            ],
        );
    }
}
