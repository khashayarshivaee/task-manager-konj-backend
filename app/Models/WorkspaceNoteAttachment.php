<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'note_id',
    'uploaded_by',
    'disk',
    'path',
    'original_name',
    'mime_type',
    'size',
])]
class WorkspaceNoteAttachment extends Model
{
    /**
     * Private storage information must not
     * be exposed through API responses.
     *
     * @var list<string>
     */
    protected $hidden = [
        'disk',
        'path',
    ];

    public function note(): BelongsTo
    {
        return $this->belongsTo(
            WorkspaceNote::class,
            'note_id',
        );
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'uploaded_by',
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
