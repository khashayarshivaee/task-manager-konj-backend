<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Search\GlobalSearchRequest;
use App\Models\User;
use App\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class GlobalSearchController extends Controller
{
    public function __construct(
        private readonly GlobalSearchService $search,
    ) {
    }

    public function index(
        GlobalSearchRequest $request,
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            Response::HTTP_UNAUTHORIZED,
        );

        $validated =
            $request->validated();

        $results =
            $this->search->search(
                $user,
                (string) $validated['q'],
                (int) $validated['limit'],
            );

        return response()->json([
            'data' => [
                'query' =>
                    $validated['q'],

                'results' =>
                    $results,
            ],
        ]);
    }
}
