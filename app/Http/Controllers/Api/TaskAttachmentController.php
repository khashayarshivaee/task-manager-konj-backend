<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\StoreTaskAttachmentRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TaskAttachmentController extends Controller
{
    private const MAX_ATTACHMENTS_PER_TASK = 10;

    /**
     * Get attachments belonging to a task.
     */
    public function index(
        Workspace $workspace,
        Project $project,
        Task $task
    ): JsonResponse {
        $this->authorizeTaskAccess(
            $workspace,
            $project,
            $task
        );

        $attachments = $task
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
     * Upload multiple images for a task.
     */
    public function store(
        StoreTaskAttachmentRequest $request,
        Workspace $workspace,
        Project $project,
        Task $task
    ): JsonResponse {
        $this->authorizeTaskAccess(
            $workspace,
            $project,
            $task
        );

        $images = $request->file('images', []);

        $existingCount = $task
            ->attachments()
            ->count();

        if (
            $existingCount + count($images) >
            self::MAX_ATTACHMENTS_PER_TASK
        ) {
            throw ValidationException::withMessages([
                'images' => [
                    'A task can contain up to 10 images.',
                ],
            ]);
        }

        $storedPaths = [];

        DB::beginTransaction();

        try {
            foreach ($images as $image) {
                $path = $image->store(
                    sprintf(
                        'task-attachments/%d/%d/%d',
                        $workspace->id,
                        $project->id,
                        $task->id
                    ),
                    'local'
                );

                $storedPaths[] = $path;

                $task->attachments()->create([
                    'uploaded_by' =>
                        $request->user()->id,

                    'disk' => 'local',
                    'path' => $path,

                    'original_name' =>
                        $image->getClientOriginalName(),

                    'mime_type' =>
                        $image->getMimeType()
                        ?? 'application/octet-stream',

                    'size' => $image->getSize(),
                ]);
            }

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();

            Storage::disk('local')->delete(
                $storedPaths
            );

            throw $exception;
        }

        $attachments = $task
            ->attachments()
            ->with([
                'uploader:id,name,email',
            ])
            ->latest('id')
            ->limit(count($images))
            ->get();

        return response()->json([
            'message' =>
                'Task images uploaded successfully.',

            'data' => [
                'attachments' => $attachments,
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Display an attachment securely.
     */
    public function file(
        Workspace $workspace,
        Project $project,
        Task $task,
        TaskAttachment $attachment
    ): BinaryFileResponse {
        $this->authorizeTaskAccess(
            $workspace,
            $project,
            $task
        );

        $this->ensureAttachmentBelongsToTask(
            $task,
            $attachment
        );

        abort_unless(
            Storage::disk($attachment->disk)
                ->exists($attachment->path),
            Response::HTTP_NOT_FOUND
        );

        $response = response()->file(
            Storage::disk($attachment->disk)
                ->path($attachment->path),
            [
                'Content-Type' =>
                    $attachment->mime_type,

                'Cache-Control' =>
                    'private, max-age=3600',
            ]
        );

        $response->setContentDisposition(
            'inline',
            $attachment->original_name
        );

        return $response;
    }

    /**
     * Delete a task attachment.
     */
    public function destroy(
        Request $request,
        Workspace $workspace,
        Project $project,
        Task $task,
        TaskAttachment $attachment
    ): JsonResponse {
        $this->authorizeTaskAccess(
            $workspace,
            $project,
            $task
        );

        $this->ensureAttachmentBelongsToTask(
            $task,
            $attachment
        );

        $this->authorizeAttachmentDeletion(
            $request->user(),
            $workspace,
            $attachment
        );

        $disk = $attachment->disk;
        $path = $attachment->path;

        $attachment->delete();

        Storage::disk($disk)->delete($path);

        return response()->json([
            'message' =>
                'Task attachment deleted successfully.',
        ]);
    }

    private function authorizeTaskAccess(
        Workspace $workspace,
        Project $project,
        Task $task
    ): void {
        abort_unless(
            $project->workspace_id ===
            $workspace->id,
            Response::HTTP_NOT_FOUND
        );

        abort_unless(
            $task->project_id === $project->id,
            Response::HTTP_NOT_FOUND
        );

        Gate::authorize(
            'view',
            $workspace
        );
    }

    private function ensureAttachmentBelongsToTask(
        Task $task,
        TaskAttachment $attachment
    ): void {
        abort_unless(
            $attachment->task_id === $task->id,
            Response::HTTP_NOT_FOUND
        );
    }

    private function authorizeAttachmentDeletion(
        User $user,
        Workspace $workspace,
        TaskAttachment $attachment
    ): void {
        if (
            $attachment->uploaded_by ===
            $user->id
        ) {
            return;
        }

        if (
            Gate::allows(
                'manageProjects',
                $workspace
            )
        ) {
            return;
        }

        abort(Response::HTTP_FORBIDDEN);
    }
}
