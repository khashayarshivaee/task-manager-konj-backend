<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VpnServerActivityService;
use Illuminate\Http\JsonResponse;

final class VpnServerActivityController extends Controller
{
    public function __invoke(
        VpnServerActivityService $activityService,
    ): JsonResponse {
        return response()->json([
            'data' => $activityService->snapshot(),
        ]);
    }
}
