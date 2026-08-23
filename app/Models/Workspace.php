<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
#[Fillable([
    'owner_id',
    'name',
    'slug',
])]
class Workspace extends Model
{
    /**
     * Get the owner of the workspace.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'owner_id'
        );
    }

    /**
     * Get all membership records.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(
            WorkspaceMembership::class
        );
    }

    /**
     * Get all users belonging to the workspace.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'workspace_memberships'
        )
            ->using(WorkspaceMembership::class)
            ->as('membership')
            ->withPivot([
                'id',
                'role',
                'joined_at',
            ])
            ->withTimestamps();
    }

    /**
     * Get projects belonging to the workspace.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Get notes belonging to the workspace.
     */
    public function notes(): HasMany
    {
        return $this->hasMany(
            WorkspaceNote::class
        );
    }

    public function instagramAccounts(): HasMany
    {
        return $this->hasMany(InstagramAccount::class);
    }
}
