<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MonitoredServer;
use Illuminate\Http\JsonResponse;

final class MonitoredServerController extends Controller
{
    public function index(): JsonResponse
    {
        $servers = MonitoredServer::query()
            ->enabled()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get([
                'id',
                'slug',
                'name',
                'region',
            ]);

        return response()->json([
            'data' => $servers,
        ], 200, [
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
