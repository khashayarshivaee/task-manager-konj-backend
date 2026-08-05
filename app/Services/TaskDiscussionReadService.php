<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder as QueryBuilder;
final class TaskDiscussionReadService
{
    /**
     * Add a watcher without modifying the read
     * position of an existing watcher.
     */
    public function ensureWatchingAtLatest(
        Task $task,
        User|int $user,
    ): void {
        $userId = $this->resolveUserId(
            $user,
        );

        $now = now();

        DB::table('task_watchers')
            ->insertOrIgnore([
                'task_id' => $task->id,
                'user_id' => $userId,

                'last_read_comment_id' =>
                    $this->latestCommentId(
                        $task,
                    ),

                'created_at' => $now,
                'updated_at' => $now,
            ]);
    }

    /**
     * Mark every current comment as read.
     */
    public function markLatestRead(
        Task $task,
        User|int $user,
    ): ?int {
        return $this->markReadThrough(
            $task,
            $user,
            $this->latestCommentId(
                $task,
            ),
        );
    }

    /**
     * Move the read position forward through
     * the provided comment.
     */
    public function markReadThrough(
        Task $task,
        User|int $user,
        ?int $commentId,
    ): ?int {
        $userId = $this->resolveUserId(
            $user,
        );

        $watcher = DB::table(
            'task_watchers',
        )
            ->where(
                'task_id',
                $task->id,
            )
            ->where(
                'user_id',
                $userId,
            )
            ->first([
                'last_read_comment_id',
            ]);

        if ($watcher === null) {
            return null;
        }

        $currentCommentId =
            $watcher->last_read_comment_id
                === null
                ? null
                : (int) $watcher
                    ->last_read_comment_id;

        if ($commentId === null) {
            return $currentCommentId;
        }

        if (
            $currentCommentId !== null
            && $currentCommentId >= $commentId
        ) {
            return $currentCommentId;
        }

        DB::table('task_watchers')
            ->where(
                'task_id',
                $task->id,
            )
            ->where(
                'user_id',
                $userId,
            )
            ->update([
                'last_read_comment_id' =>
                    $commentId,

                'updated_at' => now(),
            ]);

        return $commentId;
    }

    /**
     * Count unread, non-deleted comments written
     * by other users.
     */
    public function unreadCount(
        Task $task,
        User|int $user,
    ): int {
        $userId = $this->resolveUserId(
            $user,
        );

        $watcher = DB::table(
            'task_watchers',
        )
            ->where(
                'task_id',
                $task->id,
            )
            ->where(
                'user_id',
                $userId,
            )
            ->first([
                'last_read_comment_id',
            ]);

        if ($watcher === null) {
            return 0;
        }

        $commentsQuery =
            TaskComment::query()
                ->where(
                    'task_id',
                    $task->id,
                )
                ->where(
                    'user_id',
                    '!=',
                    $userId,
                );

        if (
            $watcher->last_read_comment_id
            !== null
        ) {
            $commentsQuery->where(
                'id',
                '>',
                (int) $watcher
                    ->last_read_comment_id,
            );
        }

        return $commentsQuery->count();
    }

    /**
     * Get personalized unread counts for all
     * current task watchers using one query.
     *
     * @return array<int, int>
     */
    public function unreadCountsForWatchers(
        Task $task,
    ): array {
        return DB::table(
            'task_watchers as watcher',
        )
            ->where(
                'watcher.task_id',
                $task->id,
            )
            ->select([
                'watcher.user_id',
            ])
            ->selectSub(
                function (
                    QueryBuilder $query,
                ): void {
                    $query
                        ->from(
                            'task_comments as comment',
                        )
                        ->selectRaw(
                            'COUNT(*)',
                        )
                        ->whereColumn(
                            'comment.task_id',
                            'watcher.task_id',
                        )
                        ->whereNull(
                            'comment.deleted_at',
                        )
                        ->whereColumn(
                            'comment.user_id',
                            '!=',
                            'watcher.user_id',
                        )
                        ->where(
                            function (
                                QueryBuilder $comments,
                            ): void {
                                $comments
                                    ->whereNull(
                                        'watcher.last_read_comment_id',
                                    )
                                    ->orWhereColumn(
                                        'comment.id',
                                        '>',
                                        'watcher.last_read_comment_id',
                                    );
                            },
                        );
                },
                'unread_count',
            )
            ->get()
            ->mapWithKeys(
                fn (object $state): array => [
                    (int) $state->user_id =>
                        (int) $state
                            ->unread_count,
                ],
            )
            ->all();
    }

    private function latestCommentId(
        Task $task,
    ): ?int {
        $commentId =
            TaskComment::query()
                ->withTrashed()
                ->where(
                    'task_id',
                    $task->id,
                )
                ->max('id');

        return $commentId === null
            ? null
            : (int) $commentId;
    }

    private function resolveUserId(
        User|int $user,
    ): int {
        return $user instanceof User
            ? (int) $user->id
            : $user;
    }
}
