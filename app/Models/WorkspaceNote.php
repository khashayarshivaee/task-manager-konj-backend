<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'workspace_id',
    'author_id',
    'title',
    'content',
    'is_pinned',
])]
class WorkspaceNote extends Model
{
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(
            Workspace::class
        );
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'author_id',
        );
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(
            WorkspaceNoteAttachment::class,
            'note_id',
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
        ];
    }
}
