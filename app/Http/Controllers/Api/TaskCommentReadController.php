<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Services\TaskCommentRealtimeService;
use App\Services\TaskDiscussionReadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class TaskCommentReadController extends Controller
{
    public function __invoke(
        Workspace $workspace,
        Project $project,
        Task $task,
        TaskDiscussionReadService $discussionRead,
        TaskCommentRealtimeService $realtime,
    ): JsonResponse {
        $user = request()->user();

        abort_unless(
            $user instanceof User,
            Response::HTTP_UNAUTHORIZED,
        );

        abort_if(
            $project->workspace_id
                !== $workspace->id,
            Response::HTTP_NOT_FOUND,
        );

        abort_if(
            $task->project_id
                !== $project->id,
            Response::HTTP_NOT_FOUND,
        );

        Gate::authorize(
            'watch',
            $task,
        );

        $lastReadCommentId =
            $discussionRead
                ->markLatestRead(
                    $task,
                    $user,
                );

        $unreadCommentsCount =
            $discussionRead
                ->unreadCount(
                    $task,
                    $user,
                );

        $realtime->broadcastRead(
            (int) $workspace->id,
            (int) $project->id,
            $task,
            (int) $user->id,
            $lastReadCommentId,
            $unreadCommentsCount,
        );

        return response()->json([
            'message' =>
                'Task comments marked as read.',

            'data' => [
                'last_read_comment_id' =>
                    $lastReadCommentId,

                'unread_comments_count' =>
                    $unreadCommentsCount,
            ],
        ]);
    }
}
