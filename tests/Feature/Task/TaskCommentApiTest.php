<?php

declare(strict_types=1);

namespace Tests\Feature\Task;

use App\Enums\WorkspaceRole;
use App\Models\TaskComment;
use App\Models\TaskCommentAttachment;
use App\Models\User;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Illuminate\Broadcasting\BroadcastManager;
use App\Models\Task;
use App\Services\TaskDiscussionReadService;
use App\Events\TaskCommentCreated;
use App\Events\TaskUnreadCommentsUpdated;
use Illuminate\Support\Facades\Event;

class TaskCommentApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private int $workspaceId;

    private int $projectId;

    private int $taskId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner =
            User::factory()->create();

        Sanctum::actingAs(
            $this->owner,
        );

        $workspaceResponse =
            $this->postJson(
                '/api/workspaces',
                [
                    'name' =>
                        'Comments Workspace',
                ],
            );

        $workspaceResponse
            ->assertCreated();

        $this->workspaceId =
            (int) $workspaceResponse->json(
                'data.workspace.id',
            );

        $projectResponse =
            $this->postJson(
                "/api/workspaces/{$this->workspaceId}/projects",
                [
                    'name' =>
                        'Comments Project',

                    'description' =>
                        'Project for comment tests.',

                    'status' =>
                        'active',
                ],
            );

        $projectResponse
            ->assertCreated();

        $this->projectId =
            (int) $projectResponse->json(
                'data.project.id',
            );

        $taskResponse =
            $this->postJson(
                "/api/workspaces/{$this->workspaceId}/projects/{$this->projectId}/tasks",
                [
                    'title' =>
                        'Comments Test Task',

                    'description' =>
                        'Task for comment API tests.',

                    'status' =>
                        'todo',

                    'priority' =>
                        'medium',

                    'assigned_to' =>
                        null,

                    'starts_at' =>
                        null,

                    'due_at' =>
                        null,
                ],
            );

        $taskResponse
            ->assertCreated();

        $this->taskId =
            (int) $taskResponse->json(
                'data.task.id',
            );
    }

    public function test_workspace_member_can_create_text_comment(): void
    {
        $response =
            $this->postJson(
                $this->commentsUrl(),
                [
                    'body' =>
                        'This is the first task comment.',
                ],
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.comment.body',
                'This is the first task comment.',
            )
            ->assertJsonPath(
                'data.comment.parent_id',
                null,
            )
            ->assertJsonPath(
                'data.comment.is_deleted',
                false,
            )
            ->assertJsonPath(
                'data.comment.author.id',
                $this->owner->id,
            );
            $commentId =
                (int) $response->json(
                    'data.comment.id',
                );

        $this->assertDatabaseHas(
            'task_comments',
            [
                'task_id' =>
                    $this->taskId,

                'user_id' =>
                    $this->owner->id,

                'parent_id' =>
                    null,

                'body' =>
                    'This is the first task comment.',
            ],
        );

        $this->assertDatabaseHas(
            'project_activities',
            [
                'actor_id' =>
                    $this->owner->id,

                'type' =>
                    'comment_created',

                'subject_type' =>
                    'comment',

                'subject_id' =>
                    $commentId,

                'subject_label' =>
                    'This is the first task comment.',
            ],
        );
    }

    public function test_workspace_member_can_create_image_only_comment(): void
    {
        Storage::fake('local');

        $image =
            UploadedFile::fake()
                ->image(
                    'design.jpg',
                    1200,
                    800,
                )
                ->size(500);

        $response =
            $this->post(
                $this->commentsUrl(),
                [
                    'images' => [
                        $image,
                    ],
                ],
                [
                    'Accept' =>
                        'application/json',
                ],
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.comment.body',
                null,
            )
            ->assertJsonCount(
                1,
                'data.comment.attachments',
            );

        $attachment =
            TaskCommentAttachment::query()
                ->firstOrFail();

        Storage::disk('local')
            ->assertExists(
                $attachment->path,
            );

        $this->assertDatabaseHas(
            'task_comment_attachments',
            [
                'id' =>
                    $attachment->id,

                'comment_id' =>
                    $attachment->comment_id,

                'uploaded_by' =>
                    $this->owner->id,

                'original_name' =>
                    'design.jpg',
            ],
        );
    }

    public function test_comment_requires_text_or_at_least_one_image(): void
    {
        $response =
            $this->postJson(
                $this->commentsUrl(),
                [
                    'body' => '   ',
                ],
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'body',
            ]);

        $this->assertDatabaseCount(
            'task_comments',
            0,
        );
    }

    public function test_workspace_member_can_reply_to_comment(): void
    {
        $parentId =
            $this->createTextComment(
                'Parent comment',
            );

        $response =
            $this->postJson(
                $this->commentsUrl(),
                [
                    'parent_id' =>
                        $parentId,

                    'body' =>
                        'Reply to parent comment',
                ],
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.comment.parent_id',
                $parentId,
            )
            ->assertJsonPath(
                'data.comment.body',
                'Reply to parent comment',
            );

        $this->assertDatabaseHas(
            'task_comments',
            [
                'task_id' =>
                    $this->taskId,

                'parent_id' =>
                    $parentId,

                'body' =>
                    'Reply to parent comment',
            ],
        );
    }

    public function test_reply_to_reply_is_kept_under_root_comment(): void
    {
        $parentId =
            $this->createTextComment(
                'Root comment',
            );

        $firstReplyResponse =
            $this->postJson(
                $this->commentsUrl(),
                [
                    'parent_id' =>
                        $parentId,

                    'body' =>
                        'First reply',
                ],
            );

        $firstReplyResponse
            ->assertCreated();

        $firstReplyId =
            (int) $firstReplyResponse->json(
                'data.comment.id',
            );

        $secondReplyResponse =
            $this->postJson(
                $this->commentsUrl(),
                [
                    'parent_id' =>
                        $firstReplyId,

                    'body' =>
                        'Reply to the first reply',
                ],
            );

        $secondReplyResponse
            ->assertCreated()
            ->assertJsonPath(
                'data.comment.parent_id',
                $parentId,
            );

        $this->assertDatabaseHas(
            'task_comments',
            [
                'body' =>
                    'Reply to the first reply',

                'parent_id' =>
                    $parentId,
            ],
        );

        $listResponse =
            $this->getJson(
                $this->commentsUrl(),
            );

        $listResponse
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.comments',
            )
            ->assertJsonCount(
                2,
                'data.comments.0.replies',
            );
    }

    public function test_comment_author_can_edit_comment(): void
    {
        $commentId =
            $this->createTextComment(
                'Original comment',
            );

        $response =
            $this->putJson(
                $this->commentUrl(
                    $commentId,
                ),
                [
                    'body' =>
                        'Updated comment',
                ],
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.comment.body',
                'Updated comment',
            )
            ->assertJsonPath(
                'data.comment.is_edited',
                true,
            );

        $this->assertDatabaseHas(
            'task_comments',
            [
                'id' =>
                    $commentId,

                'body' =>
                    'Updated comment',
            ],
        );

        $comment =
            TaskComment::query()
                ->findOrFail(
                    $commentId,
                );

        $this->assertNotNull(
            $comment->edited_at,
        );
        $this->assertDatabaseHas(
            'project_activities',
            [
                'actor_id' =>
                    $this->owner->id,

                'type' =>
                    'comment_updated',

                'subject_type' =>
                    'comment',

                'subject_id' =>
                    $commentId,

                'subject_label' =>
                    'Updated comment',
            ],
        );
    }

    public function test_member_cannot_edit_another_users_comment(): void
    {
        $commentId =
            $this->createTextComment(
                'Owner comment',
            );

        $member =
            $this->createWorkspaceMember();

        Sanctum::actingAs(
            $member,
        );
        $this->postJson(
            $this->watchUrl(),
        )->assertOk();

        $this->putJson(
            $this->commentUrl(
                $commentId,
            ),
            [
                'body' =>
                    'Unauthorized edit',
            ],
        )->assertForbidden();

        $this->assertDatabaseHas(
            'task_comments',
            [
                'id' =>
                    $commentId,

                'body' =>
                    'Owner comment',
            ],
        );
    }

    public function test_workspace_owner_can_delete_members_comment(): void
    {
        $member =
            $this->createWorkspaceMember();

        Sanctum::actingAs(
            $member,
        );
        $this->postJson(
            $this->watchUrl(),
        )->assertOk();

        $commentResponse =
            $this->postJson(
                $this->commentsUrl(),
                [
                    'body' =>
                        'Member comment',
                ],
            );

        $commentResponse
            ->assertCreated();

        $commentId =
            (int) $commentResponse->json(
                'data.comment.id',
            );

        Sanctum::actingAs(
            $this->owner,
        );

        $this->deleteJson(
            $this->commentUrl(
                $commentId,
            ),
        )->assertOk();

        $this->assertSoftDeleted(
            'task_comments',
            [
                'id' =>
                    $commentId,
            ],
        );
        $this->assertDatabaseHas(
            'project_activities',
            [
                'actor_id' =>
                    $this->owner->id,

                'type' =>
                    'comment_deleted',

                'subject_type' =>
                    'comment',

                'subject_id' =>
                    $commentId,

                'subject_label' =>
                    'Member comment',
            ],
        );
    }

    public function test_soft_deleted_parent_remains_visible_with_replies(): void
    {
        $parentId =
            $this->createTextComment(
                'Parent to delete',
            );

        $replyResponse =
            $this->postJson(
                $this->commentsUrl(),
                [
                    'parent_id' =>
                        $parentId,

                    'body' =>
                        'Reply must remain visible',
                ],
            );

        $replyResponse
            ->assertCreated();

        $replyId =
            (int) $replyResponse->json(
                'data.comment.id',
            );

        $this->deleteJson(
            $this->commentUrl(
                $parentId,
            ),
        )->assertOk();

        $response =
            $this->getJson(
                $this->commentsUrl(),
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.comments.0.id',
                $parentId,
            )
            ->assertJsonPath(
                'data.comments.0.body',
                null,
            )
            ->assertJsonPath(
                'data.comments.0.is_deleted',
                true,
            )
            ->assertJsonPath(
                'data.comments.0.replies.0.id',
                $replyId,
            )
            ->assertJsonPath(
                'data.comments.0.replies.0.body',
                'Reply must remain visible',
            );
    }

    public function test_authenticated_workspace_member_can_view_private_comment_image(): void
    {
        Storage::fake('local');

        $image =
            UploadedFile::fake()
                ->image(
                    'private-image.png',
                    900,
                    600,
                );

        $commentResponse =
            $this->post(
                $this->commentsUrl(),
                [
                    'body' =>
                        'Comment with private image',

                    'images' => [
                        $image,
                    ],
                ],
                [
                    'Accept' =>
                        'application/json',
                ],
            );

        $commentResponse
            ->assertCreated();

        $commentId =
            (int) $commentResponse->json(
                'data.comment.id',
            );

        $attachmentId =
            (int) $commentResponse->json(
                'data.comment.attachments.0.id',
            );

        $this->get(
            $this->commentAttachmentFileUrl(
                $commentId,
                $attachmentId,
            ),
            [
                'Accept' =>
                    'image/png',
            ],
        )
            ->assertOk()
            ->assertHeader(
                'content-type',
                'image/png',
            );
    }

    public function test_comment_author_can_delete_individual_image_when_text_remains(): void
    {
        Storage::fake('local');

        $image =
            UploadedFile::fake()
                ->image(
                    'removable.jpg',
                );

        $commentResponse =
            $this->post(
                $this->commentsUrl(),
                [
                    'body' =>
                        'The text will remain.',

                    'images' => [
                        $image,
                    ],
                ],
                [
                    'Accept' =>
                        'application/json',
                ],
            );

        $commentResponse
            ->assertCreated();

        $commentId =
            (int) $commentResponse->json(
                'data.comment.id',
            );

        $attachmentId =
            (int) $commentResponse->json(
                'data.comment.attachments.0.id',
            );

        $attachment =
            TaskCommentAttachment::query()
                ->findOrFail(
                    $attachmentId,
                );

        $path =
            $attachment->path;

        $this->deleteJson(
            $this->commentAttachmentUrl(
                $commentId,
                $attachmentId,
            ),
        )->assertOk();

        $this->assertDatabaseMissing(
            'task_comment_attachments',
            [
                'id' =>
                    $attachmentId,
            ],
        );

        Storage::disk('local')
            ->assertMissing(
                $path,
            );

        $this->assertDatabaseHas(
            'task_comments',
            [
                'id' =>
                    $commentId,

                'body' =>
                    'The text will remain.',
            ],
        );
        $this->assertDatabaseHas(
            'project_activities',
            [
                'actor_id' =>
                    $this->owner->id,

                'type' =>
                    'comment_attachment_removed',

                'subject_type' =>
                    'comment_attachment',

                'subject_id' =>
                    $attachmentId,

                'subject_label' =>
                    'removable.jpg',
            ],
        );
    }

    public function test_last_image_of_image_only_comment_cannot_be_deleted(): void
    {
        Storage::fake('local');

        $image =
            UploadedFile::fake()
                ->image(
                    'only-content.webp',
                );

        $commentResponse =
            $this->post(
                $this->commentsUrl(),
                [
                    'images' => [
                        $image,
                    ],
                ],
                [
                    'Accept' =>
                        'application/json',
                ],
            );

        $commentResponse
            ->assertCreated();

        $commentId =
            (int) $commentResponse->json(
                'data.comment.id',
            );

        $attachmentId =
            (int) $commentResponse->json(
                'data.comment.attachments.0.id',
            );

        $attachment =
            TaskCommentAttachment::query()
                ->findOrFail(
                    $attachmentId,
                );

        $this->deleteJson(
            $this->commentAttachmentUrl(
                $commentId,
                $attachmentId,
            ),
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'A comment must contain text or at least one image.',
            );

        $this->assertDatabaseHas(
            'task_comment_attachments',
            [
                'id' =>
                    $attachmentId,
            ],
        );

        Storage::disk('local')
            ->assertExists(
                $attachment->path,
            );
    }

    public function test_outsider_cannot_list_task_comments(): void
    {
        $outsider =
            User::factory()->create();

        Sanctum::actingAs(
            $outsider,
        );

        $this->getJson(
            $this->commentsUrl(),
        )->assertForbidden();
    }

    public function test_task_comments_cannot_be_accessed_through_another_project(): void
    {
        $otherProjectResponse =
            $this->postJson(
                "/api/workspaces/{$this->workspaceId}/projects",
                [
                    'name' =>
                        'Other Project',

                    'description' =>
                        null,

                    'status' =>
                        'active',
                ],
            );

        $otherProjectResponse
            ->assertCreated();

        $otherProjectId =
            (int) $otherProjectResponse->json(
                'data.project.id',
            );

        $this->getJson(
            "/api/workspaces/{$this->workspaceId}/projects/{$otherProjectId}/tasks/{$this->taskId}/comments",
        )->assertNotFound();
    }
    public function test_regular_member_cannot_access_comments_before_watching(): void
    {
        $member =
            $this->createWorkspaceMember();

        Sanctum::actingAs($member);

        $this->getJson(
            $this->commentsUrl(),
        )->assertForbidden();

        $this->postJson(
            $this->commentsUrl(),
            [
                'body' =>
                    'This comment must not be created.',
            ],
        )->assertForbidden();

        $this->assertDatabaseMissing(
            'task_comments',
            [
                'task_id' =>
                    $this->taskId,

                'user_id' =>
                    $member->id,
            ],
        );
    }

    public function test_regular_member_can_comment_after_watching(): void
    {
        $member =
            $this->createWorkspaceMember();

        Sanctum::actingAs($member);

        $this->postJson(
            $this->watchUrl(),
        )->assertOk();

        $this->getJson(
            $this->commentsUrl(),
        )->assertOk();

        $this->postJson(
            $this->commentsUrl(),
            [
                'body' =>
                    'Comment from a task watcher.',
            ],
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.comment.author.id',
                $member->id,
            );

        $this->assertDatabaseHas(
            'task_comments',
            [
                'task_id' =>
                    $this->taskId,

                'user_id' =>
                    $member->id,

                'body' =>
                    'Comment from a task watcher.',
            ],
        );
    }

    public function test_workspace_admin_becomes_watcher_after_commenting(): void
    {
        $admin =
            User::factory()->create();

        WorkspaceMembership::query()
            ->create([
                'workspace_id' =>
                    $this->workspaceId,

                'user_id' =>
                    $admin->id,

                'role' =>
                    WorkspaceRole::Admin
                        ->value,
            ]);

        $this->assertDatabaseMissing(
            'task_watchers',
            [
                'task_id' =>
                    $this->taskId,

                'user_id' =>
                    $admin->id,
            ],
        );

        Sanctum::actingAs($admin);

        $this->postJson(
            $this->commentsUrl(),
            [
                'body' =>
                    'Admin joined the discussion.',
            ],
        )->assertCreated();

        $this->assertDatabaseHas(
            'task_watchers',
            [
                'task_id' =>
                    $this->taskId,

                'user_id' =>
                    $admin->id,
            ],
        );
    }
    public function test_new_watcher_starts_with_existing_comments_read(): void
    {
        $commentId =
            $this->createTextComment(
                'Comment created before watching.',
            );

        $member =
            $this->createWorkspaceMember();

        Sanctum::actingAs($member);

        $this->postJson(
            $this->watchUrl(),
        )->assertOk();

        $this->assertDatabaseHas(
            'task_watchers',
            [
                'task_id' =>
                    $this->taskId,

                'user_id' =>
                    $member->id,

                'last_read_comment_id' =>
                    $commentId,
            ],
        );

        $task = Task::query()
            ->findOrFail(
                $this->taskId,
            );

        $unreadCount =
            app(
                TaskDiscussionReadService::class,
            )->unreadCount(
                $task,
                $member,
            );

        $this->assertSame(
            0,
            $unreadCount,
        );
    }

    public function test_comment_from_another_user_becomes_unread_but_own_comment_does_not(): void
    {
        $member =
            $this->createWorkspaceMember();

        Sanctum::actingAs($member);

        $this->postJson(
            $this->watchUrl(),
        )->assertOk();

        $this->postJson(
            $this->commentsUrl(),
            [
                'body' =>
                    'Comment written by the member.',
            ],
        )->assertCreated();

        $task = Task::query()
            ->findOrFail(
                $this->taskId,
            );

        $discussionRead =
            app(
                TaskDiscussionReadService::class,
            );

        $this->assertSame(
            0,
            $discussionRead->unreadCount(
                $task,
                $member,
            ),
        );

        Sanctum::actingAs(
            $this->owner,
        );

        $this->postJson(
            $this->commentsUrl(),
            [
                'body' =>
                    'Comment written by another user.',
            ],
        )->assertCreated();

        $this->assertSame(
            1,
            $discussionRead->unreadCount(
                $task,
                $member,
            ),
        );
    }

    public function test_marking_comments_as_read_only_updates_the_current_user(): void
    {
        $firstMember =
            $this->createWorkspaceMember();

        $secondMember =
            $this->createWorkspaceMember();

        Sanctum::actingAs(
            $firstMember,
        );

        $this->postJson(
            $this->watchUrl(),
        )->assertOk();

        Sanctum::actingAs(
            $secondMember,
        );

        $this->postJson(
            $this->watchUrl(),
        )->assertOk();

        Sanctum::actingAs(
            $this->owner,
        );

        $commentId =
            $this->createTextComment(
                'Unread comment for both members.',
            );

        $task = Task::query()
            ->findOrFail(
                $this->taskId,
            );

        $discussionRead =
            app(
                TaskDiscussionReadService::class,
            );

        $this->assertSame(
            1,
            $discussionRead->unreadCount(
                $task,
                $firstMember,
            ),
        );

        $this->assertSame(
            1,
            $discussionRead->unreadCount(
                $task,
                $secondMember,
            ),
        );

        Sanctum::actingAs(
            $firstMember,
        );

        $this->patchJson(
            $this->commentsReadUrl(),
        )
            ->assertOk()
            ->assertJsonPath(
                'data.last_read_comment_id',
                $commentId,
            )
            ->assertJsonPath(
                'data.unread_comments_count',
                0,
            );

        $this->assertSame(
            0,
            $discussionRead->unreadCount(
                $task,
                $firstMember,
            ),
        );

        $this->assertSame(
            1,
            $discussionRead->unreadCount(
                $task,
                $secondMember,
            ),
        );

        $this->assertDatabaseHas(
            'task_watchers',
            [
                'task_id' =>
                    $this->taskId,

                'user_id' =>
                    $firstMember->id,

                'last_read_comment_id' =>
                    $commentId,
            ],
        );
    }

    public function test_deleted_comment_is_not_counted_as_unread(): void
    {
        $member =
            $this->createWorkspaceMember();

        Sanctum::actingAs($member);

        $this->postJson(
            $this->watchUrl(),
        )->assertOk();

        Sanctum::actingAs(
            $this->owner,
        );

        $commentId =
            $this->createTextComment(
                'Comment that will be deleted.',
            );

        $task = Task::query()
            ->findOrFail(
                $this->taskId,
            );

        $discussionRead =
            app(
                TaskDiscussionReadService::class,
            );

        $this->assertSame(
            1,
            $discussionRead->unreadCount(
                $task,
                $member,
            ),
        );

        $this->deleteJson(
            $this->commentUrl(
                $commentId,
            ),
        )->assertOk();

        $this->assertSame(
            0,
            $discussionRead->unreadCount(
                $task,
                $member,
            ),
        );
    }

    public function test_creating_comment_dispatches_realtime_events_for_task_and_watchers(): void
    {
        $member =
            $this->createWorkspaceMember();

        Sanctum::actingAs($member);

        $this->postJson(
            $this->watchUrl(),
        )->assertOk();

        Event::fake([
            TaskCommentCreated::class,
            TaskUnreadCommentsUpdated::class,
        ]);

        Sanctum::actingAs(
            $this->owner,
        );

        $this->postJson(
            $this->commentsUrl(),
            [
                'body' =>
                    'Realtime comment created.',
            ],
        )->assertCreated();

        Event::assertDispatched(
            TaskCommentCreated::class,
            function (
                TaskCommentCreated $event,
            ): bool {
                return $event->workspaceId ===
                        $this->workspaceId
                    && $event->projectId ===
                        $this->projectId
                    && $event->taskId ===
                        $this->taskId
                    && $event->actorId ===
                        $this->owner->id
                    && data_get(
                        $event->comment,
                        'body',
                    ) ===
                        'Realtime comment created.';
            },
        );

        Event::assertDispatched(
            TaskUnreadCommentsUpdated::class,
            fn (
                TaskUnreadCommentsUpdated $event,
            ): bool =>
                $event->taskId ===
                    $this->taskId
                && $event->userId ===
                    $member->id
                && $event
                    ->unreadCommentsCount === 1
                && $event->reason ===
                    'comment_created',
        );

        Event::assertDispatched(
            TaskUnreadCommentsUpdated::class,
            fn (
                TaskUnreadCommentsUpdated $event,
            ): bool =>
                $event->taskId ===
                    $this->taskId
                && $event->userId ===
                    $this->owner->id
                && $event
                    ->unreadCommentsCount === 0,
        );
    }

    public function test_task_private_channel_requires_discussion_access(): void
    {
        /*
         * PHPUnit normally uses the log broadcaster,
         * whose auth method does not authorize private
         * channels. Use an isolated Reverb broadcaster
         * for this HTTP authorization test.
         */
        config()->set(
            'broadcasting.default',
            'reverb',
        );

        config()->set(
            'broadcasting.connections.reverb',
            [
                'driver' => 'reverb',

                'key' =>
                    'test-reverb-key',

                'secret' =>
                    'test-reverb-secret',

                'app_id' =>
                    'test-reverb-app',

                'options' => [
                    'host' =>
                        '127.0.0.1',

                    'port' =>
                        8080,

                    'scheme' =>
                        'http',

                    'useTLS' =>
                        false,
                ],

                'client_options' => [],
            ],
        );

        /*
         * Remove the previously resolved log driver.
         */
       app(
           BroadcastManager::class,
       )->forgetDrivers();

        /*
         * Channel callbacks were initially registered
         * on the log broadcaster during application
         * boot. Register them again on the isolated
         * Reverb broadcaster.
         *
         * This must be require, not require_once.
         */
        require base_path(
            'routes/channels.php',
        );

        $member =
            $this->createWorkspaceMember();

        Sanctum::actingAs($member);

        $this->assertDatabaseMissing(
            'task_watchers',
            [
                'task_id' =>
                    $this->taskId,

                'user_id' =>
                    $member->id,
            ],
        );

        $authorizationPayload = [
            'socket_id' =>
                '1234.5678',

            'channel_name' =>
                'private-tasks.'
                .$this->taskId,
        ];

        $this->postJson(
            '/api/broadcasting/auth',
            $authorizationPayload,
        )->assertForbidden();

        $this->postJson(
            $this->watchUrl(),
        )->assertOk();

        $this->postJson(
            '/api/broadcasting/auth',
            $authorizationPayload,
        )
            ->assertOk()
            ->assertJsonStructure([
                'auth',
            ]);
    }

    private function createTextComment(
        string $body,
    ): int {
        $response =
            $this->postJson(
                $this->commentsUrl(),
                [
                    'body' => $body,
                ],
            );

        $response
            ->assertCreated();

        return (int) $response->json(
            'data.comment.id',
        );
    }

    private function createWorkspaceMember(): User
    {
        $member =
            User::factory()->create();

        WorkspaceMembership::query()
            ->create([
                'workspace_id' =>
                    $this->workspaceId,

                'user_id' =>
                    $member->id,

                'role' =>
                    WorkspaceRole::Member
                        ->value,
            ]);

        return $member;
    }

    private function commentsUrl(): string
    {
        return "/api/workspaces/{$this->workspaceId}/projects/{$this->projectId}/tasks/{$this->taskId}/comments";
    }

    private function commentsReadUrl(): string
    {
        return $this->commentsUrl()
            .'/read';
    }

    private function watchUrl(): string
    {
        return "/api/workspaces/{$this->workspaceId}/projects/{$this->projectId}/tasks/{$this->taskId}/watch";
    }

    private function commentUrl(
        int $commentId,
    ): string {
        return $this->commentsUrl()
            ."/{$commentId}";
    }

    private function commentAttachmentUrl(
        int $commentId,
        int $attachmentId,
    ): string {
        return $this->commentUrl(
            $commentId,
        )
            ."/attachments/{$attachmentId}";
    }

    private function commentAttachmentFileUrl(
        int $commentId,
        int $attachmentId,
    ): string {
        return $this->commentAttachmentUrl(
            $commentId,
            $attachmentId,
        )
            .'/file';
    }


}
