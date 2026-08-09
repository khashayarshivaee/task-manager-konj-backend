<?php

declare(strict_types=1);

namespace Tests\Feature\Project;

use App\Enums\ProjectActivitySubjectType;
use App\Enums\ProjectActivityType;
use App\Enums\ProjectStatus;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ProjectActivityNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Events\UserNotificationCreated;
use Illuminate\Support\Facades\Event;

class ProjectActivityNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_unique_unread_recipients_and_excludes_actor(): void
    {
        $actor =
            User::factory()->create();

        $firstRecipient =
            User::factory()->create();

        $secondRecipient =
            User::factory()->create();

        $workspace =
            $this->createWorkspace(
                $actor,
            );

        $project =
            $this->createProject(
                $workspace,
                $actor,
            );

        $activity =
            ProjectActivity::query()
                ->create([
                    'project_id' =>
                        $project->id,

                    'actor_id' =>
                        $actor->id,

                    'type' =>
                        ProjectActivityType::CommentCreated,

                    'subject_type' =>
                        ProjectActivitySubjectType::Comment,

                    'subject_id' =>
                        100,

                    'subject_label' =>
                        'Test comment',

                    'metadata' => [
                        'task_id' => 50,
                    ],
                ]);

        $service = app(
            ProjectActivityNotificationService::class,
        );

        $service->notify(
            $activity,
            [
                $actor->id,
                $firstRecipient->id,
                $firstRecipient->id,
                $secondRecipient->id,
            ],
        );

        $this->assertDatabaseCount(
            'project_activity_recipients',
            2,
        );

        $this->assertDatabaseMissing(
            'project_activity_recipients',
            [
                'project_activity_id' =>
                    $activity->id,

                'user_id' =>
                    $actor->id,
            ],
        );

        $this->assertDatabaseHas(
            'project_activity_recipients',
            [
                'project_activity_id' =>
                    $activity->id,

                'user_id' =>
                    $firstRecipient->id,

                'read_at' =>
                    null,
            ],
        );

        $this->assertDatabaseHas(
            'project_activity_recipients',
            [
                'project_activity_id' =>
                    $activity->id,

                'user_id' =>
                    $secondRecipient->id,

                'read_at' =>
                    null,
            ],
        );

        /*
         * Calling notify again with the same
         * recipients must not create duplicates.
         */
        $service->notify(
            $activity,
            [
                $firstRecipient->id,
                $secondRecipient->id,
            ],
        );

        $this->assertDatabaseCount(
            'project_activity_recipients',
            2,
        );
    }

    public function test_it_dispatches_realtime_event_only_for_new_recipient(): void
    {
        Event::fake([
            UserNotificationCreated::class,
        ]);

        $actor =
            User::factory()->create();

        $recipient =
            User::factory()->create();

        $workspace =
            $this->createWorkspace(
                $actor,
            );

        $project =
            $this->createProject(
                $workspace,
                $actor,
            );

        $activity =
            ProjectActivity::query()
                ->create([
                    'project_id' =>
                        $project->id,

                    'actor_id' =>
                        $actor->id,

                    'type' =>
                        ProjectActivityType::CommentCreated,

                    'subject_type' =>
                        ProjectActivitySubjectType::Comment,

                    'subject_id' =>
                        200,

                    'subject_label' =>
                        'Realtime notification',

                    'metadata' => [
                        'task_id' =>
                            50,
                    ],
                ]);

        $service = app(
            ProjectActivityNotificationService::class,
        );

        $service->notify(
            $activity,
            [
                $recipient->id,
            ],
        );

        Event::assertDispatched(
            UserNotificationCreated::class,
            function (
                UserNotificationCreated $event,
            ) use (
                $recipient,
                $activity,
            ): bool {
                return $event->userId ===
                        $recipient->id
                    && $event->unreadCount === 1
                    && data_get(
                        $event->notification,
                        'id',
                    ) === $activity->id
                    && data_get(
                        $event->notification,
                        'notification.is_read',
                    ) === false;
            },
        );

        /*
         * Calling notify again for the same
         * activity and recipient must not broadcast
         * another notification.
         */
        $service->notify(
            $activity,
            [
                $recipient->id,
            ],
        );

        Event::assertDispatchedTimes(
            UserNotificationCreated::class,
            1,
        );
    }

    private function createWorkspace(
        User $owner,
    ): Workspace {
        $workspace =
            Workspace::query()->create([
                'owner_id' =>
                    $owner->id,

                'name' =>
                    'Notification Workspace',

                'slug' =>
                    'notification-workspace',
            ]);

        $workspace
            ->memberships()
            ->create([
                'user_id' =>
                    $owner->id,

                'role' =>
                    WorkspaceRole::Owner,

                'joined_at' =>
                    now(),
            ]);

        return $workspace;
    }

    private function createProject(
        Workspace $workspace,
        User $creator,
    ): Project {
        return Project::query()->create([
            'workspace_id' =>
                $workspace->id,

            'created_by' =>
                $creator->id,

            'name' =>
                'Notification Project',

            'slug' =>
                'notification-project',

            'status' =>
                ProjectStatus::Active,
        ]);
    }
}
