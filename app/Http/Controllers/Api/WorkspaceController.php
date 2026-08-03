<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\DeleteWorkspaceRequest;
use App\Http\Requests\Workspace\StoreWorkspaceRequest;
use App\Http\Requests\Workspace\UpdateWorkspaceRequest;
use App\Models\TaskAttachment;
use App\Models\TaskCommentAttachment;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class WorkspaceController extends Controller
{
    /**
     * Get all workspaces available to the current user.
     */
    public function index(
        Request $request
    ): JsonResponse {
        $workspaces = $request
            ->user()
            ->workspaces()
            ->with([
                'owner:id,name,email',
            ])
            ->latest('workspaces.created_at')
            ->get();

        return response()->json([
            'data' => [
                'workspaces' => $workspaces,
            ],
        ]);
    }

    /**
     * Create a workspace and register
     * the current user as its owner.
     */
    public function store(
        StoreWorkspaceRequest $request
    ): JsonResponse {
        $validated = $request->validated();
        $user = $request->user();

        $workspace = DB::transaction(
            function () use (
                $validated,
                $user
            ): Workspace {
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
            'owner:id,name,email',
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
     * Update workspace settings.
     */
    public function update(
        UpdateWorkspaceRequest $request,
        Workspace $workspace
    ): JsonResponse {
        Gate::authorize(
            'update',
            $workspace
        );

        $validated = $request->validated();

        $slug = $workspace->slug;

        if ($workspace->name !== $validated['name']) {
            $slug = $this->generateUniqueSlug(
                $validated['name'],
                $workspace
            );
        }

        $workspace->update([
            'name' => $validated['name'],
            'slug' => $slug,
        ]);

        $workspace->load([
            'owner:id,name,email',
            'memberships',
        ]);

        return response()->json([
            'message' => 'Workspace updated successfully.',

            'data' => [
                'workspace' => $workspace,
            ],
        ]);
    }

    /**
     * Permanently delete a workspace and its dependencies.
     */
    public function destroy(
        DeleteWorkspaceRequest $request,
        Workspace $workspace
    ): JsonResponse {
        Gate::authorize(
            'delete',
            $workspace
        );

        $request->validated();

        $attachmentFiles = $this
            ->collectWorkspaceAttachmentFiles(
                $workspace
            );

        DB::transaction(
            function () use ($workspace): void {
                $workspace->delete();
            }
        );

        $this->deleteStoredFiles(
            $attachmentFiles
        );

        return response()->json([
            'message' => 'Workspace deleted successfully.',
        ]);
    }

    /**
     * Generate a globally unique workspace slug.
     */
    private function generateUniqueSlug(
        string $name,
        ?Workspace $ignoredWorkspace = null
    ): string {
        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = 'workspace';
        }

        $slug = $baseSlug;
        $counter = 2;

        while (
            $this->workspaceSlugExists(
                $slug,
                $ignoredWorkspace
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
     * Check whether the workspace slug already exists.
     */
    private function workspaceSlugExists(
        string $slug,
        ?Workspace $ignoredWorkspace = null
    ): bool {
        $query = Workspace::query()
            ->where('slug', $slug);

        if ($ignoredWorkspace !== null) {
            $query->where(
                'id',
                '!=',
                $ignoredWorkspace->id
            );
        }

        return $query->exists();
    }

    /**
     * Collect all private task and comment files
     * belonging to the workspace before database deletion.
     *
     * @return array<int, array{
     *     disk: string,
     *     path: string
     * }>
     */
    private function collectWorkspaceAttachmentFiles(
        Workspace $workspace
    ): array {
        $taskAttachments = TaskAttachment::query()
            ->join(
                'tasks',
                'tasks.id',
                '=',
                'task_attachments.task_id'
            )
            ->join(
                'projects',
                'projects.id',
                '=',
                'tasks.project_id'
            )
            ->where(
                'projects.workspace_id',
                $workspace->id
            )
            ->get([
                'task_attachments.disk as disk',
                'task_attachments.path as path',
            ])
            ->map(
                static fn (
                    TaskAttachment $attachment
                ): array => [
                    'disk' => (string) $attachment->disk,
                    'path' => (string) $attachment->path,
                ]
            );

        $commentAttachments =
            TaskCommentAttachment::query()
                ->join(
                    'task_comments',
                    'task_comments.id',
                    '=',
                    'task_comment_attachments.comment_id'
                )
                ->join(
                    'tasks',
                    'tasks.id',
                    '=',
                    'task_comments.task_id'
                )
                ->join(
                    'projects',
                    'projects.id',
                    '=',
                    'tasks.project_id'
                )
                ->where(
                    'projects.workspace_id',
                    $workspace->id
                )
                ->get([
                    'task_comment_attachments.disk as disk',
                    'task_comment_attachments.path as path',
                ])
                ->map(
                    static fn (
                        TaskCommentAttachment $attachment
                    ): array => [
                        'disk' => (string) $attachment->disk,
                        'path' => (string) $attachment->path,
                    ]
                );

        return $taskAttachments
            ->merge($commentAttachments)
            ->values()
            ->all();
    }

    /**
     * Delete collected files after the database
     * transaction has completed successfully.
     *
     * @param array<int, array{
     *     disk: string,
     *     path: string
     * }> $files
     */
    private function deleteStoredFiles(
        array $files
    ): void {
        $filesByDisk = collect($files)
            ->groupBy('disk');

        foreach (
            $filesByDisk as $disk => $diskFiles
        ) {
            try {
                Storage::disk(
                    (string) $disk
                )->delete(
                    $diskFiles
                        ->pluck('path')
                        ->all()
                );
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
