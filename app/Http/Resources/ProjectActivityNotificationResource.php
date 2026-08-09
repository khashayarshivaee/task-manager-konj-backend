<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ProjectActivityRecipient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProjectActivityNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public static function payload(
        ProjectActivityRecipient $recipient,
    ): array {
        $recipient->loadMissing([
            'activity.actor:id,name,email,avatar_path',
            'activity.project:id,workspace_id',
        ]);

        $activity =
            $recipient->activity;

        return [
            ...ProjectActivityResource::payload(
                $activity,
            ),

            'workspace_id' =>
                (int) $activity
                    ->project
                    ->workspace_id,

            'notification' => [
                'id' =>
                    $recipient->id,

                'read_at' =>
                    $recipient->read_at,

                'is_read' =>
                    $recipient->read_at !== null,
            ],
        ];
    }

    public function toArray(Request $request): array
    {
        /** @var ProjectActivityRecipient $recipient */
        $recipient = $this->resource;

        return self::payload($recipient);
    }
}
