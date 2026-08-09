<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'password',
])]
#[Hidden([
    'password',
    'remember_token',
])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Check whether the user is a system administrator.
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Check whether the user's account is active.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Get workspaces owned by the user.
     */
    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(
            Workspace::class,
            'owner_id'
        );
    }

    /**
     * Get the user's workspace membership records.
     */
    public function workspaceMemberships(): HasMany
    {
        return $this->hasMany(
            WorkspaceMembership::class
        );
    }

    /**
     * Get workspaces the user belongs to.
     */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(
            Workspace::class,
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
     /**
      * Get projects created by the user.
      */
     public function createdProjects(): HasMany
     {
         return $this->hasMany(
             Project::class,
             'created_by'
         );
     }
     /**
      * Get the user's project membership records.
      */
     public function projectMemberships(): HasMany
     {
         return $this->hasMany(
             ProjectMembership::class
         );
     }

     /**
      * Get projects the user belongs to.
      */
     public function projects(): BelongsToMany
     {
         return $this->belongsToMany(
             Project::class,
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
      * Get tasks created by the user.
      */
     public function createdTasks(): HasMany
     {
         return $this->hasMany(
             Task::class,
             'created_by'
         );
     }

     /**
      * Get tasks assigned to the user.
      */
    /**
     * Get tasks assigned to the user.
     */
    public function assignedTasks(): BelongsToMany
    {
        return $this->belongsToMany(
            Task::class,
            'task_assignees'
        )
            ->withPivot([
                'id',
                'assigned_by',
            ])
            ->withTimestamps();
    }

 /**
  * Get tasks watched by the user.
  */
 public function watchedTasks(): BelongsToMany
 {
     return $this->belongsToMany(
         Task::class,
         'task_watchers',
     )
         ->withPivot([
             'id',
             'last_read_comment_id',
         ])
         ->withTimestamps();
 }

 /**
  * Get activity notifications for the user.
  */
 public function activityRecipients(): HasMany
 {
     return $this->hasMany(
         ProjectActivityRecipient::class,
         'user_id',
     );
 }

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
