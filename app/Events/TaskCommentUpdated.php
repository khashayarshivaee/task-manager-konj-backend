<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TaskCommentUpdated implements
    ShouldBroadcast,
    ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param array<string, mixed> $comment
     */
    public function __construct(
        public readonly int $workspaceId,
        public readonly int $projectId,
        public readonly int $taskId,
        public readonly int $actorId,
        public readonly array $comment,
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
        return 'task.comment.updated';
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

            'comment' =>
                $this->comment,
        ];
    }
}
