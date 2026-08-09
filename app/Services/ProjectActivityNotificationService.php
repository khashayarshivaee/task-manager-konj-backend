<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\UserNotificationCreated;
use App\Http\Resources\ProjectActivityNotificationResource;
use App\Models\ProjectActivity;
use App\Models\ProjectActivityRecipient;
use Illuminate\Database\Eloquent\Collection;

final class ProjectActivityNotificationService
{
    /**
     * Attach notification recipients to an activity.
     *
     * @param iterable<int> $userIds
     * @return Collection<int, ProjectActivityRecipient>
     */
    public function notify(
        ProjectActivity $activity,
        iterable $userIds,
    ): Collection {
        $recipientIds = collect($userIds)
            ->map(
                static fn ($userId): int =>
                    (int) $userId,
            )
            ->filter(
                static fn (int $userId): bool =>
                    $userId > 0,
            )
            ->when(
                $activity->actor_id !== null,
                fn ($ids) => $ids->reject(
                    fn (int $userId): bool =>
                        $userId ===
                        (int) $activity->actor_id,
                ),
            )
            ->unique()
            ->values();

        if ($recipientIds->isEmpty()) {
            return new Collection();
        }

        $activity->loadMissing([
            'actor:id,name,email,avatar_path',
        ]);

        $recipients =
            new Collection();

        foreach ($recipientIds as $userId) {
            $now = now();

            $inserted =
                ProjectActivityRecipient::query()
                    ->insertOrIgnore([
                        'project_activity_id' =>
                            (int) $activity->id,

                        'user_id' =>
                            $userId,

                        'read_at' =>
                            null,

                        'created_at' =>
                            $now,

                        'updated_at' =>
                            $now,
                    ]);

            $recipient =
                ProjectActivityRecipient::query()
                    ->where(
                        'project_activity_id',
                        $activity->id,
                    )
                    ->where(
                        'user_id',
                        $userId,
                    )
                    ->firstOrFail();

            $recipient->setRelation(
                'activity',
                $activity,
            );

            $recipients->push(
                $recipient,
            );

            /*
             * Do not broadcast again when the
             * recipient already existed.
             */
            if ($inserted === 0) {
                continue;
            }

            $unreadCount =
                ProjectActivityRecipient::query()
                    ->where(
                        'user_id',
                        $userId,
                    )
                    ->whereNull(
                        'read_at',
                    )
                    ->count();

            UserNotificationCreated::dispatch(
                $userId,
                ProjectActivityNotificationResource::payload(
                    $recipient,
                ),
                $unreadCount,
            );
        }

        return $recipients;
    }
}
