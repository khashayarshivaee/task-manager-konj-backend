<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TaskCommentsRead implements
    ShouldBroadcast,
    ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $workspaceId,
        public readonly int $projectId,
        public readonly int $taskId,
        public readonly int $userId,
        public readonly ?int $lastReadCommentId,
        public readonly int $unreadCommentsCount,
    ) {
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(
                'users.'.$this->userId,
            ),
        ];
    }

    public function broadcastAs(): string
    {
        return 'task.comments.read';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'workspace_id' =>
                $this->workspaceId,

            'project_id' =>
                $this->projectId,

            'task_id' =>
                $this->taskId,

            'user_id' =>
                $this->userId,

            'last_read_comment_id' =>
                $this->lastReadCommentId,

            'unread_comments_count' =>
                $this->unreadCommentsCount,
        ];
    }
}
