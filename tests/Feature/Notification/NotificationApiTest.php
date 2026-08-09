<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Enums\ProjectActivitySubjectType;
use App\Enums\ProjectActivityType;
use App\Enums\ProjectStatus;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectActivityRecipient;
use App\Models\User;
use App\Models\Workspace;
use App\Events\UserNotificationsReadUpdated;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_only_own_notifications(): void
    {
        $user =
            User::factory()->create();

        $otherUser =
            User::factory()->create();

        $workspace =
            $this->createWorkspace(
                $user,
            );

        $project =
            $this->createProject(
                $workspace,
                $user,
            );

        $firstActivity =
            $this->createActivity(
                $project,
                $user,
                'First notification',
                101,
            );

        $secondActivity =
            $this->createActivity(
                $project,
                $otherUser,
                'Second notification',
                102,
            );

        $otherActivity =
            $this->createActivity(
                $project,
                $user,
                'Other user notification',
                103,
            );

        ProjectActivityRecipient::query()
            ->create([
                'project_activity_id' =>
                    $firstActivity->id,

                'user_id' =>
                    $user->id,
            ]);

        ProjectActivityRecipient::query()
            ->create([
                'project_activity_id' =>
                    $secondActivity->id,

                'user_id' =>
                    $user->id,

                'read_at' =>
                    now(),
            ]);

        ProjectActivityRecipient::query()
            ->create([
                'project_activity_id' =>
                    $otherActivity->id,

                'user_id' =>
                    $otherUser->id,
            ]);

        Sanctum::actingAs(
            $user,
        );

        $this->getJson(
            '/api/notifications',
        )
            ->assertOk()
            ->assertJsonCount(
                2,
                'data.notifications',
            )
            ->assertJsonPath(
                'data.unread_count',
                1,
            )
            ->assertJsonPath(
                'meta.total',
                2,
            );
    }

    public function test_user_can_get_unread_notification_count(): void
    {
        $user =
            User::factory()->create();

        $workspace =
            $this->createWorkspace(
                $user,
            );

        $project =
            $this->createProject(
                $workspace,
                $user,
            );

        $firstActivity =
            $this->createActivity(
                $project,
                $user,
                'Unread one',
                201,
            );

        $secondActivity =
            $this->createActivity(
                $project,
                $user,
                'Unread two',
                202,
            );

        ProjectActivityRecipient::query()
            ->create([
                'project_activity_id' =>
                    $firstActivity->id,

                'user_id' =>
                    $user->id,
            ]);

        ProjectActivityRecipient::query()
            ->create([
                'project_activity_id' =>
                    $secondActivity->id,

                'user_id' =>
                    $user->id,
            ]);

        Sanctum::actingAs(
            $user,
        );

        $this->getJson(
            '/api/notifications/unread-count',
        )
            ->assertOk()
            ->assertJsonPath(
                'data.unread_count',
                2,
            );
    }

    public function test_user_can_mark_own_notification_as_read(): void
    {
        $user =
            User::factory()->create();

        $workspace =
            $this->createWorkspace(
                $user,
            );

        $project =
            $this->createProject(
                $workspace,
                $user,
            );

        $activity =
            $this->createActivity(
                $project,
                $user,
                'Read notification',
                301,
            );

        $recipient =
            ProjectActivityRecipient::query()
                ->create([
                    'project_activity_id' =>
                        $activity->id,

                    'user_id' =>
                        $user->id,
                ]);

        Sanctum::actingAs(
            $user,
        );

        $this->patchJson(
            "/api/notifications/"
            ."{$recipient->id}"
            .'/read',
        )
            ->assertOk()
            ->assertJsonPath(
                'data.notification.notification.id',
                $recipient->id,
            )
            ->assertJsonPath(
                'data.notification.notification.is_read',
                true,
            );

        $this->assertDatabaseMissing(
            'project_activity_recipients',
            [
                'id' =>
                    $recipient->id,

                'read_at' =>
                    null,
            ],
        );
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $user =
            User::factory()->create();

        $otherUser =
            User::factory()->create();

        $workspace =
            $this->createWorkspace(
                $user,
            );

        $project =
            $this->createProject(
                $workspace,
                $user,
            );

        $activity =
            $this->createActivity(
                $project,
                $user,
                'Private notification',
                401,
            );

        $recipient =
            ProjectActivityRecipient::query()
                ->create([
                    'project_activity_id' =>
                        $activity->id,

                    'user_id' =>
                        $otherUser->id,
                ]);

        Sanctum::actingAs(
            $user,
        );

        $this->patchJson(
            "/api/notifications/"
            ."{$recipient->id}"
            .'/read',
        )->assertNotFound();

        $this->assertDatabaseHas(
            'project_activity_recipients',
            [
                'id' =>
                    $recipient->id,

                'read_at' =>
                    null,
            ],
        );
    }

    public function test_read_all_only_marks_current_users_notifications_as_read(): void
    {
        $user =
            User::factory()->create();

        $otherUser =
            User::factory()->create();

        $workspace =
            $this->createWorkspace(
                $user,
            );

        $project =
            $this->createProject(
                $workspace,
                $user,
            );

        $userActivity =
            $this->createActivity(
                $project,
                $user,
                'Current user notification',
                501,
            );

        $otherActivity =
            $this->createActivity(
                $project,
                $user,
                'Other user notification',
                502,
            );

        $userRecipient =
            ProjectActivityRecipient::query()
                ->create([
                    'project_activity_id' =>
                        $userActivity->id,

                    'user_id' =>
                        $user->id,
                ]);

        $otherRecipient =
            ProjectActivityRecipient::query()
                ->create([
                    'project_activity_id' =>
                        $otherActivity->id,

                    'user_id' =>
                        $otherUser->id,
                ]);

        Sanctum::actingAs(
            $user,
        );

        $this->patchJson(
            '/api/notifications/read-all',
        )
            ->assertOk()
            ->assertJsonPath(
                'data.unread_count',
                0,
            );

        $this->assertDatabaseMissing(
            'project_activity_recipients',
            [
                'id' =>
                    $userRecipient->id,

                'read_at' =>
                    null,
            ],
        );

        $this->assertDatabaseHas(
            'project_activity_recipients',
            [
                'id' =>
                    $otherRecipient->id,

                'read_at' =>
                    null,
            ],
        );
    }

    public function test_marking_notification_as_read_dispatches_realtime_event(): void
    {
        Event::fake([
            UserNotificationsReadUpdated::class,
        ]);

        $user =
            User::factory()->create();

        $workspace =
            $this->createWorkspace(
                $user,
            );

        $project =
            $this->createProject(
                $workspace,
                $user,
            );

        $firstActivity =
            $this->createActivity(
                $project,
                $user,
                'First unread notification',
                601,
            );

        $secondActivity =
            $this->createActivity(
                $project,
                $user,
                'Second unread notification',
                602,
            );

        $recipient =
            ProjectActivityRecipient::query()
                ->create([
                    'project_activity_id' =>
                        $firstActivity->id,

                    'user_id' =>
                        $user->id,
                ]);

        ProjectActivityRecipient::query()
            ->create([
                'project_activity_id' =>
                    $secondActivity->id,

                'user_id' =>
                    $user->id,
            ]);

        Sanctum::actingAs(
            $user,
        );

        $this->patchJson(
            "/api/notifications/{$recipient->id}/read",
        )
            ->assertOk()
            ->assertJsonPath(
                'data.unread_count',
                1,
            );

        Event::assertDispatched(
            UserNotificationsReadUpdated::class,
            fn (
                UserNotificationsReadUpdated $event,
            ): bool =>
                $event->userId === $user->id
                && $event->notificationId ===
                    $recipient->id
                && $event->unreadCount === 1
                && $event->readAll === false,
        );

        $this->patchJson(
            "/api/notifications/{$recipient->id}/read",
        )->assertOk();

        Event::assertDispatchedTimes(
            UserNotificationsReadUpdated::class,
            1,
        );
    }

    public function test_marking_all_notifications_as_read_dispatches_realtime_event(): void
    {
        Event::fake([
            UserNotificationsReadUpdated::class,
        ]);

        $user =
            User::factory()->create();

        $workspace =
            $this->createWorkspace(
                $user,
            );

        $project =
            $this->createProject(
                $workspace,
                $user,
            );

        $firstActivity =
            $this->createActivity(
                $project,
                $user,
                'Read all first',
                701,
            );

        $secondActivity =
            $this->createActivity(
                $project,
                $user,
                'Read all second',
                702,
            );

        ProjectActivityRecipient::query()
            ->create([
                'project_activity_id' =>
                    $firstActivity->id,

                'user_id' =>
                    $user->id,
            ]);

        ProjectActivityRecipient::query()
            ->create([
                'project_activity_id' =>
                    $secondActivity->id,

                'user_id' =>
                    $user->id,
            ]);

        Sanctum::actingAs(
            $user,
        );

        $this->patchJson(
            '/api/notifications/read-all',
        )
            ->assertOk()
            ->assertJsonPath(
                'data.unread_count',
                0,
            );

        Event::assertDispatched(
            UserNotificationsReadUpdated::class,
            fn (
                UserNotificationsReadUpdated $event,
            ): bool =>
                $event->userId === $user->id
                && $event->notificationId === null
                && $event->unreadCount === 0
                && $event->readAll === true,
        );

        $this->patchJson(
            '/api/notifications/read-all',
        )->assertOk();

        Event::assertDispatchedTimes(
            UserNotificationsReadUpdated::class,
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
                    'Notification API Workspace',

                'slug' =>
                    'notification-api-workspace',
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
                'Notification API Project',

            'slug' =>
                'notification-api-project',

            'status' =>
                ProjectStatus::Active,
        ]);
    }

    private function createActivity(
        Project $project,
        User $actor,
        string $label,
        int $subjectId,
    ): ProjectActivity {
        return ProjectActivity::query()
            ->create([
                'project_id' =>
                    $project->id,

                'actor_id' =>
                    $actor->id,

                'type' =>
                    ProjectActivityType::TaskUpdated,

                'subject_type' =>
                    ProjectActivitySubjectType::Task,

                'subject_id' =>
                    $subjectId,

                'subject_label' =>
                    $label,

                'metadata' => [
                    'task_id' =>
                        $subjectId,
                ],
            ]);
    }
}
