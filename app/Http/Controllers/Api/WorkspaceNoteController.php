<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkspaceNote\StoreWorkspaceNoteRequest;
use App\Http\Requests\WorkspaceNote\UpdateWorkspaceNoteRequest;
use App\Models\Workspace;
use App\Models\WorkspaceNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class WorkspaceNoteController extends Controller
{
    /**
     * List notes belonging to a workspace.
     */
    public function index(
        Request $request,
        Workspace $workspace,
    ): JsonResponse {
        Gate::authorize(
            'viewAny',
            [
                WorkspaceNote::class,
                $workspace,
            ],
        );

        $search = trim(
            (string) $request->query(
                'search',
                '',
            )
        );

        $notes = $workspace
            ->notes()
            ->with([
                'author:id,name,email,avatar_path',
                'attachments.uploader:id,name,email',
            ])
            ->when(
                $search !== '',
                function ($query) use ($search): void {
                    $query->where(
                        function ($query) use ($search): void {
                            $query
                                ->where(
                                    'title',
                                    'like',
                                    '%' . $search . '%',
                                )
                                ->orWhere(
                                    'content',
                                    'like',
                                    '%' . $search . '%',
                                );
                        },
                    );
                },
            )
            ->orderByDesc('is_pinned')
            ->orderByDesc('updated_at')
            ->paginate(20);

        return response()->json([
            'data' => [
                'notes' => $notes->items(),
            ],

            'meta' => [
                'current_page' =>
                    $notes->currentPage(),

                'last_page' =>
                    $notes->lastPage(),

                'per_page' =>
                    $notes->perPage(),

                'total' =>
                    $notes->total(),
            ],
        ]);
    }

    /**
     * Create a workspace note.
     */
    public function store(
        StoreWorkspaceNoteRequest $request,
        Workspace $workspace,
    ): JsonResponse {
        Gate::authorize(
            'create',
            [
                WorkspaceNote::class,
                $workspace,
            ],
        );

        $note = $workspace
            ->notes()
            ->create([
                ...$request->validated(),

                'author_id' =>
                    $request->user()->id,
            ]);

        $note->load([
            'author:id,name,email,avatar_path',
            'attachments.uploader:id,name,email',
        ]);

        return response()->json([
            'message' =>
                'Note created successfully.',

            'data' => [
                'note' => $note,
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Display a workspace note.
     */
    public function show(
        Workspace $workspace,
        WorkspaceNote $note,
    ): JsonResponse {
        $this->ensureNoteBelongsToWorkspace(
            $workspace,
            $note,
        );

        Gate::authorize(
            'view',
            $note,
        );

        $note->load([
            'author:id,name,email,avatar_path',
            'attachments.uploader:id,name,email',
        ]);

        return response()->json([
            'data' => [
                'note' => $note,
            ],
        ]);
    }

    /**
     * Update a workspace note.
     */
    public function update(
        UpdateWorkspaceNoteRequest $request,
        Workspace $workspace,
        WorkspaceNote $note,
    ): JsonResponse {
        $this->ensureNoteBelongsToWorkspace(
            $workspace,
            $note,
        );

        Gate::authorize(
            'update',
            $note,
        );

        $note->update(
            $request->validated()
        );

        $note->load([
            'author:id,name,email,avatar_path',
            'attachments.uploader:id,name,email',
        ]);

        return response()->json([
            'message' =>
                'Note updated successfully.',

            'data' => [
                'note' => $note,
            ],
        ]);
    }

    /**
     * Delete a workspace note.
     */
    public function destroy(
        Workspace $workspace,
        WorkspaceNote $note,
    ): JsonResponse {
        $this->ensureNoteBelongsToWorkspace(
            $workspace,
            $note,
        );

        Gate::authorize(
            'delete',
            $note,
        );

        $attachments = $note
            ->attachments()
            ->get([
                'disk',
                'path',
            ]);

        $note->delete();

        foreach ($attachments as $attachment) {
            Storage::disk(
                $attachment->disk,
            )->delete(
                $attachment->path,
            );
        }

        return response()->json([
            'message' =>
                'Note deleted successfully.',
        ]);
    }

    private function ensureNoteBelongsToWorkspace(
        Workspace $workspace,
        WorkspaceNote $note,
    ): void {
        abort_unless(
            $note->workspace_id ===
            $workspace->id,
            Response::HTTP_NOT_FOUND,
        );
    }
}
