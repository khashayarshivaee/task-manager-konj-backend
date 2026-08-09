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
        return [
            ...ProjectActivityResource::payload(
                $recipient->activity,
            ),

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

    /**
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request,
    ): array {
        /** @var ProjectActivityRecipient $recipient */
        $recipient = $this->resource;

        return self::payload(
            $recipient,
        );
    }
}
