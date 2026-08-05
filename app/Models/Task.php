<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
#[Fillable([
    'project_id',
    'created_by',
    'assigned_to',
    'title',
    'description',
    'status',
    'priority',
    'starts_at',
    'due_at',
    'completed_at',
    'position',
])]
class Task extends Model
{
    public function project(): BelongsTo
    {
        return $this->belongsTo(
            Project::class
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    /**
     * Legacy single-assignee relation.
     *
     * This relation remains temporarily while
     * the API and frontend migrate to assignees().
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'assigned_to'
        );
    }

    /**
     * Get all users assigned to the task.
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'task_assignees'
        )
            ->withPivot([
                'id',
                'assigned_by',
            ])
            ->withTimestamps();
    }

    /**
     * Get users watching the task.
     */
    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'task_watchers'
        )
          ->withPivot([
              'id',
              'last_read_comment_id',
          ])
            ->withTimestamps();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(
            TaskComment::class
        );
    }

    /**
     * Add the authenticated user's unread comment
     * count to every selected task.
     */
    public function scopeWithUnreadCommentsCount(
        Builder $query,
        int $userId,
    ): Builder {
        $commentsQuery = DB::table(
            'task_comments',
        )
            ->join(
                'task_watchers',
                function (
                    JoinClause $join,
                ) use ($userId): void {
                    $join
                        ->on(
                            'task_watchers.task_id',
                            '=',
                            'task_comments.task_id',
                        )
                        ->where(
                            'task_watchers.user_id',
                            '=',
                            $userId,
                        );
                },
            )
            ->selectRaw('COUNT(*)')
            ->whereColumn(
                'task_comments.task_id',
                'tasks.id',
            )
            ->whereNull(
                'task_comments.deleted_at',
            )
            ->where(
                'task_comments.user_id',
                '!=',
                $userId,
            )
            ->where(
                function ($comments): void {
                    $comments
                        ->whereNull(
                            'task_watchers.last_read_comment_id',
                        )
                        ->orWhereColumn(
                            'task_comments.id',
                            '>',
                            'task_watchers.last_read_comment_id',
                        );
                },
            );

        return $query
            ->select('tasks.*')
            ->selectSub(
                $commentsQuery,
                'unread_comments_count',
            );
    }

    /**
     * Get images attached to the task.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(
            TaskAttachment::class
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'starts_at' => 'date',
            'due_at' => 'date',
            'completed_at' => 'datetime',
            'position' => 'integer',
            'unread_comments_count' => 'integer',
        ];
    }
}
