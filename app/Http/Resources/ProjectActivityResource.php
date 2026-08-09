<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        return [
            'id' =>
                $this->id,

            'project_id' =>
                $this->project_id,

            'type' =>
                $this->type->value,

            'subject_type' =>
                $this->subject_type?->value,

            'subject_id' =>
                $this->subject_id,

            'subject_label' =>
                $this->subject_label,

            'metadata' =>
                $this->metadata,

            'created_at' =>
                $this->created_at,

            'actor' =>
                $this->actor === null
                    ? null
                    : [
                        'id' =>
                            $this->actor->id,

                        'name' =>
                            $this->actor->name,

                        'email' =>
                            $this->actor->email,

                        'avatar_path' =>
                            $this->actor
                                ->avatar_path,
                    ],
        ];
    }
}
