<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\ProjectActivityType;
use App\Events\UserNotificationCreated;
use App\Models\PushDevice;
use App\Services\ApnsPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

final class SendUserNotificationPush implements ShouldQueue
{
    public function __construct(
        private readonly ApnsPushService $apnsPushService,
    ) {
    }

    public function handle(
        UserNotificationCreated $event,
    ): void {
        $devices =
            PushDevice::query()
                ->where(
                    'user_id',
                    $event->userId,
                )
                ->where(
                    'platform',
                    'ios',
                )
                ->where(
                    'is_active',
                    true,
                )
                ->get();

        if ($devices->isEmpty()) {
            return;
        }

        $title =
            'Task Manager Konj';

        $body =
            $this->buildBody(
                $event->notification,
            );

        $data =
            array_filter(
                [
                    'notification_id' =>
                        data_get(
                            $event->notification,
                            'notification.id',
                        ),

                    'workspace_id' =>
                        $event->notification[
                            'workspace_id'
                        ] ?? null,

                    'project_id' =>
                        $event->notification[
                            'project_id'
                        ] ?? null,

                    'activity_type' =>
                        $event->notification[
                            'type'
                        ] ?? null,

                    'subject_type' =>
                        $event->notification[
                            'subject_type'
                        ] ?? null,

                    'subject_id' =>
                        $event->notification[
                            'subject_id'
                        ] ?? null,
                ],
                static fn (
                    mixed $value,
                ): bool =>
                    $value !== null,
            );

        foreach ($devices as $device) {
            try {
                $this->apnsPushService
                    ->sendToDevice(
                        $device,
                        $title,
                        $body,
                        $data,
                        $event->unreadCount,
                    );
            } catch (Throwable $exception) {
                /*
                 * A failed device must not prevent
                 * push delivery to other devices.
                 */
                report($exception);
            }
        }
    }

    /**
     * @param array<string, mixed> $notification
     */
    private function buildBody(
        array $notification,
    ): string {
        $actorName =
            trim(
                (string) data_get(
                    $notification,
                    'actor.name',
                    '',
                ),
            );

        if ($actorName === '') {
            $actorName =
                'Someone';
        }

        $subjectLabel =
            trim(
                (string) (
                    $notification[
                        'subject_label'
                    ] ?? ''
                ),
            );

        if ($subjectLabel === '') {
            $subjectLabel =
                'an item';
        }

        $type =
            ProjectActivityType::tryFrom(
                (string) (
                    $notification[
                        'type'
                    ] ?? ''
                ),
            );

        return match ($type) {
            ProjectActivityType::ProjectCreated =>
                "{$actorName} created project {$subjectLabel}.",

            ProjectActivityType::ProjectUpdated =>
                "{$actorName} updated project {$subjectLabel}.",

            ProjectActivityType::ProjectMemberAdded =>
                "{$actorName} added a project member.",

            ProjectActivityType::ProjectMemberRemoved =>
                "{$actorName} removed a project member.",

            ProjectActivityType::TaskCreated =>
                "{$actorName} created task {$subjectLabel}.",

            ProjectActivityType::TaskUpdated =>
                "{$actorName} updated task {$subjectLabel}.",

            ProjectActivityType::TaskStatusChanged =>
                "{$actorName} changed the status of {$subjectLabel}.",

            ProjectActivityType::TaskAssigneesChanged =>
                "{$actorName} updated assignees for {$subjectLabel}.",

            ProjectActivityType::TaskDeleted =>
                "{$actorName} deleted task {$subjectLabel}.",

            ProjectActivityType::CommentCreated =>
                "{$actorName} added a comment on {$subjectLabel}.",

            ProjectActivityType::CommentUpdated =>
                "{$actorName} updated a comment on {$subjectLabel}.",

            ProjectActivityType::CommentDeleted =>
                "{$actorName} deleted a comment on {$subjectLabel}.",

            ProjectActivityType::TaskAttachmentAdded =>
                "{$actorName} added an attachment to {$subjectLabel}.",

            ProjectActivityType::TaskAttachmentRemoved =>
                "{$actorName} removed an attachment from {$subjectLabel}.",

            ProjectActivityType::CommentAttachmentAdded =>
                "{$actorName} added an image to a comment on {$subjectLabel}.",

            ProjectActivityType::CommentAttachmentRemoved =>
                "{$actorName} removed an image from a comment on {$subjectLabel}.",

            default =>
                "{$actorName} updated {$subjectLabel}.",
        };
    }
}
