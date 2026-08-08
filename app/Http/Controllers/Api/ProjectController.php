<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
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

        $project
            ->memberships()
            ->create([
                'user_id' =>
                    $request->user()->id,

                'added_by' =>
                    $request->user()->id,

                'joined_at' => now(),
            ]);

        return response()->json([
            'message' => 'Project created successfully.',

            'data' => [
                'project' => $project,
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Get a single project.
     */
    public function show(
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

        $project->load([
            'creator:id,name,email',
        ]);

        return response()->json([
            'data' => [
                'project' => $project,
            ],
        ]);
    }

    /**
     * Update a project.
     */
    public function update(
        UpdateProjectRequest $request,
        Workspace $workspace,
        Project $project
    ): JsonResponse {
        $this->ensureProjectBelongsToWorkspace(
            $workspace,
            $project
        );

        Gate::authorize(
            'manageProjects',
            $workspace
        );

        $validated = $request->validated();

        $slug = $project->slug;

        if ($project->name !== $validated['name']) {
            $slug = $this->generateUniqueSlug(
                $workspace,
                $validated['name'],
                $project
            );
        }

        $project->update([
            'name' => $validated['name'],
            'slug' => $slug,

            'description' =>
                $validated['description'] ?? null,

            'status' => $validated['status'],

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
            'message' => 'Project updated successfully.',

            'data' => [
                'project' => $project,
            ],
        ]);
    }


    /**
     * Delete a project.
     */
    public function destroy(
        Workspace $workspace,
        Project $project
    ): JsonResponse {
        $this->ensureProjectBelongsToWorkspace(
            $workspace,
            $project
        );

        Gate::authorize(
            'manageProjects',
            $workspace
        );

        $project->delete();

        return response()->json([
            'message' => 'Project deleted successfully.',
        ]);
    }

    /**
     * Ensure that the nested project belongs to the workspace.
     */
    private function ensureProjectBelongsToWorkspace(
        Workspace $workspace,
        Project $project
    ): void {
        abort_unless(
            $project->workspace_id === $workspace->id,
            Response::HTTP_NOT_FOUND
        );
    }

    /**
     * Generate a unique project slug within a workspace.
     */
    private function generateUniqueSlug(
        Workspace $workspace,
        string $name,
        ?Project $ignoredProject = null
    ): string {
        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = 'project';
        }

        $slug = $baseSlug;
        $counter = 2;

        while (
            $this->projectSlugExists(
                $workspace,
                $slug,
                $ignoredProject
            )
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

    /**
     * Check whether a project slug already exists.
     */
    private function projectSlugExists(
        Workspace $workspace,
        string $slug,
        ?Project $ignoredProject = null
    ): bool {
        $query = $workspace
            ->projects()
            ->where('slug', $slug);

        if ($ignoredProject !== null) {
            $query->where(
                'id',
                '!=',
                $ignoredProject->id
            );
        }

        return $query->exists();
    }
}
