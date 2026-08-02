<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'task_id',
    'uploaded_by',
    'disk',
    'path',
    'original_name',
    'mime_type',
    'size',
])]
class TaskAttachment extends Model
{
    /**
     * Storage information must not be exposed
     * through API responses.
     *
     * @var list<string>
     */
    protected $hidden = [
        'disk',
        'path',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'uploaded_by'
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }
}
