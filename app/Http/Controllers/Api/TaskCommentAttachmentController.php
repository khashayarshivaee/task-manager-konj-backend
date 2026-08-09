<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskCommentAttachment;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Gate;
use App\Enums\ProjectActivitySubjectType;
use App\Enums\ProjectActivityType;
use App\Services\ProjectActivityLogger;

class TaskCommentAttachmentController extends Controller
{

    public function __construct(
        private readonly ProjectActivityLogger $activityLogger,
    ) {
    }
    public function file(
        Workspace $workspace,
        Project $project,
        Task $task,
        TaskComment $comment,
        TaskCommentAttachment $attachment,
    ): StreamedResponse {
        $user = request()->user();

        abort_unless(
            $user instanceof User,
            401,
        );

        $this->assertAttachmentContext(
            $workspace,
            $project,
            $task,
            $comment,
            $attachment,
            $user,
        );
        Gate::authorize(
            'participateInDiscussion',
            $task,
        );

        abort_unless(
            Storage::disk(
                $attachment->disk,
            )->exists(
                $attachment->path,
            ),
            404,
        );

        return Storage::disk(
            $attachment->disk,
        )->response(
            $attachment->path,
            $attachment->original_name,
            [
                'Content-Type' =>
                    $attachment->mime_type,

                'Content-Disposition' =>
                    'inline; filename="'
                    .addslashes(
                        $attachment
                            ->original_name,
                    )
                    .'"',
            ],
        );
    }

    public function destroy(
        Workspace $workspace,
        Project $project,
        Task $task,
        TaskComment $comment,
        TaskCommentAttachment $attachment,
    ): JsonResponse {
        $user = request()->user();

        abort_unless(
            $user instanceof User,
            401,
        );

        $this->assertAttachmentContext(
            $workspace,
            $project,
            $task,
            $comment,
            $attachment,
            $user,
        );
        Gate::authorize(
            'participateInDiscussion',
            $task,
        );

        abort_unless(
            $attachment->uploaded_by
                === $user->id
            || $comment->user_id
                === $user->id
            || $this->isWorkspaceManager(
                $workspace,
                $user,
            ),
            403,
        );

        $attachmentId =
            $attachment->id;

        $attachmentName =
            $attachment->original_name;

        $attachmentMimeType =
            $attachment->mime_type;

        $attachmentSize =
            $attachment->size;

        $remainingContent =
            trim(
                (string) $comment->body,
            ) !== ''
            || $comment
                ->attachments()
                ->whereKeyNot(
                    $attachment->id,
                )
                ->exists();

        if (!$remainingContent) {
            return response()->json(
                [
                    'message' =>
                        'A comment must contain text or at least one image.',
                ],
                422,
            );
        }

        Storage::disk(
            $attachment->disk,
        )->delete(
            $attachment->path,
        );

        $attachment->delete();

        $this->activityLogger->log(
            project: $project,
            type:
                ProjectActivityType::CommentAttachmentRemoved,
            actor: $user,
            subjectType:
                ProjectActivitySubjectType::CommentAttachment,
            subjectId:
                $attachmentId,
            subjectLabel:
                $attachmentName,
            metadata: [
                'task_id' =>
                    $task->id,

                'comment_id' =>
                    $comment->id,

                'mime_type' =>
                    $attachmentMimeType,

                'size' =>
                    $attachmentSize,
            ],
        );

        return response()->json([
            'message' =>
                'Comment image deleted successfully.',
        ]);
    }

    private function assertAttachmentContext(
        Workspace $workspace,
        Project $project,
        Task $task,
        TaskComment $comment,
        TaskCommentAttachment $attachment,
        User $user,
    ): void {
        abort_if(
            $project->workspace_id
                !== $workspace->id,
            404,
        );

        abort_if(
            $task->project_id
                !== $project->id,
            404,
        );

        abort_if(
            $comment->task_id
                !== $task->id,
            404,
        );

        abort_if(
            $attachment->comment_id
                !== $comment->id,
            404,
        );

        abort_unless(
            $workspace->owner_id ===
                $user->id
            || WorkspaceMembership::query()
                ->where(
                    'workspace_id',
                    $workspace->id,
                )
                ->where(
                    'user_id',
                    $user->id,
                )
                ->exists(),
            403,
        );
    }

    private function isWorkspaceManager(
        Workspace $workspace,
        User $user,
    ): bool {
        if (
            $workspace->owner_id ===
            $user->id
        ) {
            return true;
        }

        return WorkspaceMembership::query()
            ->where(
                'workspace_id',
                $workspace->id,
            )
            ->where(
                'user_id',
                $user->id,
            )
            ->whereIn(
                'role',
                [
                    WorkspaceRole::Owner
                        ->value,

                    WorkspaceRole::Admin
                        ->value,
                ],
            )
            ->exists();
    }
}
