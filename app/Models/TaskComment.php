<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
class TaskComment extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'task_id',
        'user_id',
        'parent_id',
        'body',
        'edited_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(
            Task::class,
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
        );
    }

    public function parent(): BelongsTo
    {
        return $this
            ->belongsTo(
                self::class,
                'parent_id',
            )
            ->withTrashed();
    }

    public function replies(): HasMany
    {
        return $this
            ->hasMany(
                self::class,
                'parent_id',
            )
            ->withTrashed()
            ->oldest('id');
    }

    public function repliesRecursive(): HasMany
    {
        return $this
            ->replies()
            ->with([
                'user:id,name,avatar_path',

                'attachments' => fn (
                    HasMany $query,
                ) => $query
                    ->oldest('id')
                    ->with(
                        'uploader:id,name',
                    ),

                'voiceMessage' => fn (
                    HasOne $query,
                ) => $query->with(
                    'uploader:id,name',
                ),

                'repliesRecursive',
            ]);


    }


    public function attachments(): HasMany
    {
        return $this
            ->hasMany(
                TaskCommentAttachment::class,
                'comment_id',
            )
            ->oldest('id');
    }

    public function voiceMessage(): HasOne
    {
        return $this->hasOne(
            TaskCommentVoiceMessage::class,
            'comment_id',
        );
    }
}
