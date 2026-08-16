<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Enums\WorkspaceRole;
use Illuminate\Http\JsonResponse;

class TaskCommentVoiceMessageController extends Controller
{
    public function file(
        Workspace $workspace,
        Project $project,
        Task $task,
        TaskComment $comment,
    ): BinaryFileResponse {
        $user = request()->user();

        abort_unless(
            $user instanceof User,
            401,
        );

        $this->assertCommentContext(
            $workspace,
            $project,
            $task,
            $comment,
            $user,
        );

        Gate::authorize(
            'participateInDiscussion',
            $task,
        );

        $voiceMessage =
            $comment
                ->voiceMessage()
                ->firstOrFail();

        $disk =
            Storage::disk(
                $voiceMessage->disk,
            );

        abort_unless(
            $disk->exists(
                $voiceMessage->path,
            ),
            404,
        );

        return response()->file(
            $disk->path(
                $voiceMessage->path,
            ),
            [
                'Content-Type' =>
                    $voiceMessage->mime_type,

                'Content-Disposition' =>
                    'inline; filename="'
                    .addslashes(
                        $voiceMessage
                            ->original_name,
                    )
                    .'"',

                'Accept-Ranges' =>
                    'bytes',
            ],
        );
    }

    public function destroy(
        Workspace $workspace,
        Project $project,
        Task $task,
        TaskComment $comment,
    ): JsonResponse {
        $user = request()->user();

        abort_unless(
            $user instanceof User,
            401,
        );

        $this->assertCommentContext(
            $workspace,
            $project,
            $task,
            $comment,
            $user,
        );

        Gate::authorize(
            'participateInDiscussion',
            $task,
        );

        $voiceMessage =
            $comment
                ->voiceMessage()
                ->firstOrFail();

        abort_unless(
            $voiceMessage->uploaded_by
            === $user->id
            || $comment->user_id
            === $user->id
            || $this->isWorkspaceManager(
                $workspace,
                $user,
            ),
            403,
        );

        $remainingContent =
            trim(
                (string) $comment->body,
            ) !== ''
            || $comment
                ->attachments()
                ->exists();

        if (!$remainingContent) {
            return response()->json(
                [
                    'message' =>
                        'A comment must contain text, at least one image, or a voice message.',
                ],
                422,
            );
        }

        Storage::disk(
            $voiceMessage->disk,
        )->delete(
            $voiceMessage->path,
        );

        $voiceMessage->delete();

        return response()->json([
            'message' =>
                'Comment voice message deleted successfully.',
        ]);
    }

    private function assertCommentContext(
        Workspace $workspace,
        Project $project,
        Task $task,
        TaskComment $comment,
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
