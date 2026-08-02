<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ProjectController extends Controller
{
    /**
     * Get projects belonging to the workspace.
     */
    public function index(
        Workspace $workspace
    ): JsonResponse {
        Gate::authorize(
            'view',
            $workspace
        );

        $projects = $workspace
            ->projects()
            ->with([
                'creator:id,name,email',
            ])
            ->latest('id')
            ->get();

        return response()->json([
            'data' => [
                'projects' => $projects,
            ],
        ]);
    }

    /**
     * Create a project inside the workspace.
     */
    public function store(
        StoreProjectRequest $request,
        Workspace $workspace
    ): JsonResponse {
        Gate::authorize(
            'manageProjects',
            $workspace
        );

        $validated = $request->validated();

        $project = $workspace
            ->projects()
            ->create([
                'created_by' => $request->user()->id,
                'name' => $validated['name'],
                'slug' => $this->generateUniqueSlug(
                    $workspace,
                    $validated['name']
                ),
                'description' =>
                    $validated['description'] ?? null,
                'status' =>
                    $validated['status']
                    ?? ProjectStatus::Planning,
                'color' =>
                    $validated['color'] ?? null,
                'starts_at' =>
                    $validated['starts_at'] ?? null,
                'due_at' =>
                    $validated['due_at'] ?? null,
            ]);

        $project->load([
            'creator:id,name,email',
        ]);

        return response()->json([
            'message' => 'Project created successfully.',
            'data' => [
                'project' => $project,
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Generate a unique project slug within a workspace.
     */
    private function generateUniqueSlug(
        Workspace $workspace,
        string $name
    ): string {
        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = 'project';
        }

        $slug = $baseSlug;
        $counter = 2;

        while (
            $workspace
                ->projects()
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = sprintf(
                '%s-%d',
                $baseSlug,
                $counter
            );

            $counter++;
        }

        return $slug;
    }
}
