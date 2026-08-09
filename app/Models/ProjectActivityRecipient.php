<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProjectActivityRecipient extends Model
{
    protected $fillable = [
        'project_activity_id',
        'user_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(
            ProjectActivity::class,
            'project_activity_id',
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
        );
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
