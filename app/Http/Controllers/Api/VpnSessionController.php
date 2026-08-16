<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VpnSessionService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class VpnSessionController extends Controller
{
    public function __invoke(
        VpnSessionService $vpnSessionService,
    ): JsonResponse {
        try {
            $data = $vpnSessionService->getActiveSessions();
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json(
                [
                    'message' => 'Unable to load VPN sessions.',
                ],
                503,
            );
        }

        return response()->json([
            'data' => $data,
        ]);
    }
}
