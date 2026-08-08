<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;


#[Fillable([
    'workspace_id',
    'created_by',
    'name',
    'slug',
    'description',
    'status',
    'color',
    'starts_at',
    'due_at',
])]
class Project extends Model
{
    /**
     * Get the workspace containing the project.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get the user who created the project.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    /**
     * Get all project membership records.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(
            ProjectMembership::class
        );
    }

    /**
     * Get all users belonging to the project team.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'project_memberships'
        )
            ->using(ProjectMembership::class)
            ->as('membership')
            ->withPivot([
                'id',
                'added_by',
                'joined_at',
            ])
            ->withTimestamps();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'starts_at' => 'date',
            'due_at' => 'date',
        ];
    }

    /**
     * Get tasks belonging to the project.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
