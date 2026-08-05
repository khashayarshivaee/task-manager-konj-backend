<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\TaskCommentCreated;
use App\Events\TaskCommentDeleted;
use App\Events\TaskCommentsRead;
use App\Events\TaskCommentUpdated;
use App\Events\TaskUnreadCommentsUpdated;
use App\Models\Task;

final class TaskCommentRealtimeService
{
    public function __construct(
        private readonly TaskDiscussionReadService $discussionRead,
    ) {
    }

    /**
     * @param array<string, mixed> $comment
     */
    public function broadcastCreated(
        int $workspaceId,
        int $projectId,
        Task $task,
        int $actorId,
        array $comment,
    ): void {
        TaskCommentCreated::dispatch(
            $workspaceId,
            $projectId,
            (int) $task->id,
            $actorId,
            $comment,
        );

        $this->broadcastUnreadCounts(
            $workspaceId,
            $projectId,
            $task,
            'comment_created',
        );
    }

    /**
     * @param array<string, mixed> $comment
     */
    public function broadcastUpdated(
        int $workspaceId,
        int $projectId,
        Task $task,
        int $actorId,
        array $comment,
    ): void {
        TaskCommentUpdated::dispatch(
            $workspaceId,
            $projectId,
            (int) $task->id,
            $actorId,
            $comment,
        );
    }

    public function broadcastDeleted(
        int $workspaceId,
        int $projectId,
        Task $task,
        int $actorId,
        int $commentId,
        ?int $parentId,
    ): void {
        TaskCommentDeleted::dispatch(
            $workspaceId,
            $projectId,
            (int) $task->id,
            $actorId,
            $commentId,
            $parentId,
        );

        $this->broadcastUnreadCounts(
            $workspaceId,
            $projectId,
            $task,
            'comment_deleted',
        );
    }

    public function broadcastRead(
        int $workspaceId,
        int $projectId,
        Task $task,
        int $userId,
        ?int $lastReadCommentId,
        int $unreadCommentsCount,
    ): void {
        TaskCommentsRead::dispatch(
            $workspaceId,
            $projectId,
            (int) $task->id,
            $userId,
            $lastReadCommentId,
            $unreadCommentsCount,
        );

        TaskUnreadCommentsUpdated::dispatch(
            $workspaceId,
            $projectId,
            (int) $task->id,
            $userId,
            $unreadCommentsCount,
            'comments_read',
        );
    }

    private function broadcastUnreadCounts(
        int $workspaceId,
        int $projectId,
        Task $task,
        string $reason,
    ): void {
        $unreadCounts =
            $this->discussionRead
                ->unreadCountsForWatchers(
                    $task,
                );

        foreach (
            $unreadCounts
            as $userId => $unreadCount
        ) {
            TaskUnreadCommentsUpdated::dispatch(
                $workspaceId,
                $projectId,
                (int) $task->id,
                $userId,
                $unreadCount,
                $reason,
            );
        }
    }
}
