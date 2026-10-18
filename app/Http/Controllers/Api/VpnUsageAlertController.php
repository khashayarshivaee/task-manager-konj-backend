<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable;

final class VpnUsageAlertController extends Controller
{
    /**
     * List VPN usage notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:50',
            ],
        ]);

        $alerts = DB::table('vpn_usage_alerts')
            ->where('recipient_user_id', $userId)
            ->orderByDesc('id')
            ->paginate(
                (int) ($validated['per_page'] ?? 20),
                [
                    'id',
                    'vpn_user_id',
                    'vpn_user_name',
                    'usage_date',
                    'threshold_gb',
                    'total_bytes',
                    'read_at',
                    'created_at',
                ],
                'page',
                (int) ($validated['page'] ?? 1),
            );

        $items = $alerts->getCollection()
            ->map(static function (object $alert): array {
                return [
                    'id' => (int) $alert->id,

                    'vpn_user_id' =>
                        $alert->vpn_user_id === null
                            ? null
                            : (int) $alert->vpn_user_id,

                    'vpn_user_name' =>
                        (string) $alert->vpn_user_name,

                    'usage_date' =>
                        (string) $alert->usage_date,

                    'threshold_gb' =>
                        (int) $alert->threshold_gb,

                    'total_bytes' =>
                        (int) $alert->total_bytes,

                    'read_at' => $alert->read_at === null
                        ? null
                        : CarbonImmutable::parse(
                            $alert->read_at,
                            'UTC',
                        )->toIso8601String(),

                    'is_read' =>
                        $alert->read_at !== null,

                    'created_at' =>
                        CarbonImmutable::parse(
                            $alert->created_at,
                            'UTC',
                        )->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'alerts' => $items,
                'unread_count' =>
                    $this->unreadCountFor($userId),
            ],

            'meta' => [
                'current_page' =>
                    $alerts->currentPage(),

                'per_page' =>
                    $alerts->perPage(),

                'total' =>
                    $alerts->total(),

                'last_page' =>
                    $alerts->lastPage(),

                'has_more_pages' =>
                    $alerts->hasMorePages(),
            ],
        ]);
    }

    /**
     * Get unread VPN alert count.
     */
    public function unreadCount(
        Request $request,
    ): JsonResponse {
        return response()->json([
            'data' => [
                'unread_count' =>
                    $this->unreadCountFor(
                        (int) $request->user()->id,
                    ),
            ],
        ]);
    }

    /**
     * Mark one VPN alert as read.
     */
    public function read(
        Request $request,
        int $alert,
    ): JsonResponse {
        $userId = (int) $request->user()->id;

        $query = DB::table('vpn_usage_alerts')
            ->where('id', $alert)
            ->where('recipient_user_id', $userId);

        abort_unless(
            (clone $query)->exists(),
            404,
        );

        $query
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => 'VPN alert marked as read.',
            'data' => [
                'unread_count' =>
                    $this->unreadCountFor($userId),
            ],
        ]);
    }

    /**
     * Mark all VPN alerts as read.
     */
    public function readAll(
        Request $request,
    ): JsonResponse {
        $userId = (int) $request->user()->id;

        DB::table('vpn_usage_alerts')
            ->where('recipient_user_id', $userId)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => 'All VPN alerts marked as read.',
            'data' => [
                'unread_count' => 0,
            ],
        ]);
    }

    private function unreadCountFor(int $userId): int
    {
        return DB::table('vpn_usage_alerts')
            ->where('recipient_user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }
}
