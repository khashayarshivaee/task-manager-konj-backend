<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProjectActivitySubjectType;
use App\Enums\ProjectActivityType;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\User;

final class ProjectActivityLogger
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function log(
        Project $project,
        ProjectActivityType $type,
        ?User $actor = null,
        ?ProjectActivitySubjectType $subjectType = null,
        ?int $subjectId = null,
        ?string $subjectLabel = null,
        array $metadata = [],
    ): ProjectActivity {
        return $project
            ->activities()
            ->create([
                'actor_id' =>
                    $actor?->id,

                'type' =>
                    $type,

                'subject_type' =>
                    $subjectType,

                'subject_id' =>
                    $subjectId,

                'subject_label' =>
                    $subjectLabel,

                'metadata' =>
                    $metadata === []
                        ? null
                        : $metadata,
            ]);
    }
}
