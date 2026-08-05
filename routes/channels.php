<?php

declare(strict_types=1);

use App\Models\Task;
use App\Models\User;
use App\Policies\TaskPolicy;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel(
    'users.{userId}',
    function (
        User $user,
        int $userId,
    ): bool {
        return (int) $user->id ===
            $userId;
    },
);

Broadcast::channel(
    'tasks.{taskId}',
    function (
        User $user,
        int $taskId,
    ): bool {
        $task = Task::query()
            ->find($taskId);

        if (!$task instanceof Task) {
            return false;
        }

        return app(
            TaskPolicy::class,
        )->participateInDiscussion(
            $user,
            $task,
        );
    },
);
