<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskStatus: string
{
    case Backlog = 'backlog';
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case InReview = 'in_review';
    case Completed = 'completed';

    public function isCompleted(): bool
    {
        return $this === self::Completed;
    }
}
