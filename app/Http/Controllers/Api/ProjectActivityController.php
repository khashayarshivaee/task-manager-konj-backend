<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\ListProjectActivitiesRequest;
use App\Http\Resources\ProjectActivityResource;
use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ProjectActivityController extends Controller
{
    public function index(
        ListProjectActivitiesRequest $request,
        Workspace $workspace,
        Project $project
    ): JsonResponse {
        $this->ensureProjectBelongsToWorkspace(
            $workspace,
            $project
        );

        Gate::authorize(
            'view',
            $workspace
        );

        $validated =
            $request->validated();

        $activities = $project
            ->activities()
            ->with([
                'actor:id,name,email,avatar_path',
            ])
            ->latest('id')
            ->paginate(
                (int) $validated['per_page'],
                ['*'],
                'page',
                (int) $validated['page']
            );

        return response()->json([
            'data' => [
                'activities' =>
                    ProjectActivityResource::collection(
                        $activities->items()
                    )->resolve($request),
            ],

            'meta' => [
                'current_page' =>
                    $activities->currentPage(),

                'per_page' =>
                    $activities->perPage(),

                'total' =>
                    $activities->total(),

                'last_page' =>
                    $activities->lastPage(),

                'from' =>
                    $activities->firstItem(),

                'to' =>
                    $activities->lastItem(),

                'has_more_pages' =>
                    $activities
                        ->hasMorePages(),
            ],
        ]);
    }

    private function ensureProjectBelongsToWorkspace(
        Workspace $workspace,
        Project $project
    ): void {
        abort_unless(
            $project->workspace_id ===
                $workspace->id,
            Response::HTTP_NOT_FOUND
        );
    }
}
