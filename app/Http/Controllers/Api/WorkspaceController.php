<?php

namespace App\Http\Controllers\Api;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\StoreWorkspaceRequest;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class WorkspaceController extends Controller
{
    /**
     * Create a workspace and register the current user as its owner.
     */
    public function store(
        StoreWorkspaceRequest $request
    ): JsonResponse {
        $validated = $request->validated();
        $user = $request->user();

        $workspace = DB::transaction(
            function () use ($validated, $user): Workspace {
                $workspace = Workspace::query()->create([
                    'owner_id' => $user->id,
                    'name' => $validated['name'],
                    'slug' => $this->generateUniqueSlug(
                        $validated['name']
                    ),
                ]);

                $workspace->memberships()->create([
                    'user_id' => $user->id,
                    'role' => WorkspaceRole::Owner,
                    'joined_at' => now(),
                ]);

                return $workspace;
            }
        );

        $workspace->load([
            'owner',
            'memberships',
        ]);

        return response()->json([
            'message' => 'Workspace created successfully.',
            'data' => [
                'workspace' => $workspace,
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Generate a unique slug for the workspace.
     */
    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = 'workspace';
        }

        $slug = $baseSlug;
        $counter = 2;

        while (
            Workspace::query()
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
