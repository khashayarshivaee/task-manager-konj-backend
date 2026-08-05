<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TaskCommentDeleted implements
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
        public readonly int $actorId,
        public readonly int $commentId,
        public readonly ?int $parentId,
    ) {
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(
                'tasks.'.$this->taskId,
            ),
        ];
    }

    public function broadcastAs(): string
    {
        return 'task.comment.deleted';
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

            'actor_id' =>
                $this->actorId,

            'comment_id' =>
                $this->commentId,

            'parent_id' =>
                $this->parentId,
        ];
    }
}
