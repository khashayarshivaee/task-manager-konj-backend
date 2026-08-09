<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ProjectActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public static function payload(
        ProjectActivity $activity,
    ): array {
        return [
            'id' =>
                $activity->id,

            'project_id' =>
                $activity->project_id,

            'type' =>
                $activity->type->value,

            'subject_type' =>
                $activity->subject_type?->value,

            'subject_id' =>
                $activity->subject_id,

            'subject_label' =>
                $activity->subject_label,

            'metadata' =>
                $activity->metadata,

            'created_at' =>
                $activity->created_at,

            'actor' =>
                $activity->actor === null
                    ? null
                    : [
                        'id' =>
                            $activity->actor->id,

                        'name' =>
                            $activity->actor->name,

                        'email' =>
                            $activity->actor->email,

                        'avatar_path' =>
                            $activity->actor
                                ->avatar_path,
                    ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request,
    ): array {
        /** @var ProjectActivity $activity */
        $activity = $this->resource;

        return self::payload(
            $activity,
        );
    }
}
