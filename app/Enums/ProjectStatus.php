<?php

declare(strict_types=1);

namespace App\Enums;

enum ProjectStatus: string
{
    case Planning = 'planning';
    case Active = 'active';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Archived = 'archived';

    /**
     * Check whether the project is finished or archived.
     */
    public function isClosed(): bool
    {
        return in_array($this, [
            self::Completed,
            self::Archived,
        ], true);
    }
}
