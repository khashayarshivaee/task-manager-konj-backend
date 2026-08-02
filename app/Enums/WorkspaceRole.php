<?php

declare(strict_types=1);

namespace App\Enums;

enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    /**
     * Check whether the role can manage workspace members.
     */
    public function canManageMembers(): bool
    {
        return in_array($this, [
            self::Owner,
            self::Admin,
        ], true);
    }

    /**
     * Check whether the role owns the workspace.
     */
    public function isOwner(): bool
    {
        return $this === self::Owner;
    }
}
