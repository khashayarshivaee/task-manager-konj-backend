<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable([
    'project_id',
    'user_id',
    'added_by',
    'joined_at',
])]
class ProjectMembership extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'project_memberships';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * Get the project associated with the membership.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(
            Project::class
        );
    }

    /**
     * Get the user belonging to the project.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }

    /**
     * Get the user who added this member
     * to the project.
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'added_by'
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }
}
