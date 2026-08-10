<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkspaceNote\StoreWorkspaceNoteAttachmentRequest;
use App\Models\Workspace;
use App\Models\WorkspaceNote;
use App\Models\WorkspaceNoteAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class WorkspaceNoteAttachmentController extends Controller
{
    private const MAX_ATTACHMENTS_PER_NOTE = 10;

    /**
     * List images belonging to a note.
     */
    public function index(
        Workspace $workspace,
        WorkspaceNote $note,
    ): JsonResponse {
        $this->authorizeNoteAccess(
            $workspace,
            $note,
        );

        $attachments = $note
            ->attachments()
            ->with([
                'uploader:id,name,email',
            ])
            ->latest('id')
            ->get();

        return response()->json([
            'data' => [
                'attachments' => $attachments,
            ],
        ]);
    }

    /**
     * Upload multiple images to a note.
     */
    public function store(
        StoreWorkspaceNoteAttachmentRequest $request,
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

        $images = $request->file(
            'images',
            [],
        );

        $existingCount = $note
            ->attachments()
            ->count();

        if (
            $existingCount + count($images) >
            self::MAX_ATTACHMENTS_PER_NOTE
        ) {
            throw ValidationException::withMessages([
                'images' => [
                    'A note can contain up to 10 images.',
                ],
            ]);
        }

        $storedPaths = [];

        DB::beginTransaction();

        try {
            foreach ($images as $image) {
                $path = $image->store(
                    sprintf(
                        'workspace-note-attachments/%d/%d',
                        $workspace->id,
                        $note->id,
                    ),
                    'local',
                );

                $storedPaths[] = $path;

                $note->attachments()->create([
                    'uploaded_by' =>
                        $request->user()->id,

                    'disk' => 'local',

                    'path' => $path,

                    'original_name' =>
                        $image
                            ->getClientOriginalName(),

                    'mime_type' =>
                        $image->getMimeType()
                        ?? 'application/octet-stream',

                    'size' =>
                        $image->getSize(),
                ]);
            }

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();

            Storage::disk('local')
                ->delete($storedPaths);

            throw $exception;
        }

        $attachments = $note
            ->attachments()
            ->with([
                'uploader:id,name,email',
            ])
            ->latest('id')
            ->limit(count($images))
            ->get();

        return response()->json([
            'message' =>
                'Note images uploaded successfully.',

            'data' => [
                'attachments' => $attachments,
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Display an image securely.
     */
    public function file(
        Workspace $workspace,
        WorkspaceNote $note,
        WorkspaceNoteAttachment $attachment,
    ): BinaryFileResponse {
        $this->authorizeNoteAccess(
            $workspace,
            $note,
        );

        $this->ensureAttachmentBelongsToNote(
            $note,
            $attachment,
        );

        abort_unless(
            Storage::disk($attachment->disk)
                ->exists($attachment->path),
            Response::HTTP_NOT_FOUND,
        );

        $response = response()->file(
            Storage::disk($attachment->disk)
                ->path($attachment->path),
            [
                'Content-Type' =>
                    $attachment->mime_type,

                'Cache-Control' =>
                    'private, max-age=3600',
            ],
        );

        $response->setContentDisposition(
            'inline',
            $attachment->original_name,
        );

        return $response;
    }

    /**
     * Delete an image belonging to a note.
     */
    public function destroy(
        Request $request,
        Workspace $workspace,
        WorkspaceNote $note,
        WorkspaceNoteAttachment $attachment,
    ): JsonResponse {
        $this->ensureNoteBelongsToWorkspace(
            $workspace,
            $note,
        );

        $this->ensureAttachmentBelongsToNote(
            $note,
            $attachment,
        );

        Gate::authorize(
            'update',
            $note,
        );

        $disk = $attachment->disk;
        $path = $attachment->path;

        $attachment->delete();

        Storage::disk($disk)
            ->delete($path);

        return response()->json([
            'message' =>
                'Note image deleted successfully.',
        ]);
    }

    private function authorizeNoteAccess(
        Workspace $workspace,
        WorkspaceNote $note,
    ): void {
        $this->ensureNoteBelongsToWorkspace(
            $workspace,
            $note,
        );

        Gate::authorize(
            'view',
            $note,
        );
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

    private function ensureAttachmentBelongsToNote(
        WorkspaceNote $note,
        WorkspaceNoteAttachment $attachment,
    ): void {
        abort_unless(
            $attachment->note_id ===
            $note->id,
            Response::HTTP_NOT_FOUND,
        );
    }
}
