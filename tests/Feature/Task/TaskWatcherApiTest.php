<?php

declare(strict_types=1);

namespace Tests\Feature\Task;

use App\Enums\WorkspaceRole;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskWatcherApiTest extends TestCase
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
                        'Watcher Workspace',
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
                        'Watcher Project',

                    'description' =>
                        'Project for watcher tests.',

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
                $this->tasksUrl(),
                [
                    'title' =>
                        'Watcher Test Task',

                    'description' =>
                        'Task for watcher API tests.',

                    'status' =>
                        'todo',

                    'priority' =>
                        'medium',

                    'assignee_ids' => [],

                    'starts_at' => null,
                    'due_at' => null,
                ],
            );

        $taskResponse
            ->assertCreated();

        $this->taskId =
            (int) $taskResponse->json(
                'data.task.id',
            );
    }

    public function test_workspace_member_can_watch_task(): void
    {
        $member =
            $this->createWorkspaceMember();

        Sanctum::actingAs($member);

        $this->postJson(
            $this->watchUrl(),
        )
            ->assertOk()
            ->assertJsonPath(
                'data.is_watching',
                true,
            )
            ->assertJsonPath(
                'data.can_comment',
                true,
            )
            ->assertJsonPath(
                'data.can_unwatch',
                true,
            );

        $this->assertDatabaseHas(
            'task_watchers',
            [
                'task_id' =>
                    $this->taskId,

                'user_id' =>
                    $member->id,
            ],
        );
    }

    public function test_outsider_cannot_watch_task(): void
    {
        $outsider =
            User::factory()->create();

        Sanctum::actingAs($outsider);

        $this->postJson(
            $this->watchUrl(),
        )->assertForbidden();

        $this->assertDatabaseMissing(
            'task_watchers',
            [
                'task_id' =>
                    $this->taskId,

                'user_id' =>
                    $outsider->id,
            ],
        );
    }

    public function test_task_creator_cannot_unwatch(): void
    {
        Sanctum::actingAs(
            $this->owner,
        );

        $this->deleteJson(
            $this->watchUrl(),
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'The task creator must continue watching this task.',
            );
    }

    public function test_assignee_cannot_unwatch(): void
    {
        $member =
            $this->createWorkspaceMember();

        $task = Task::query()
            ->findOrFail(
                $this->taskId,
            );

        $task
            ->assignees()
            ->sync([
                $member->id => [
                    'assigned_by' =>
                        $this->owner->id,
                ],
            ]);

        $task
            ->watchers()
            ->syncWithoutDetaching([
                $member->id,
            ]);

        Sanctum::actingAs($member);

        $this->deleteJson(
            $this->watchUrl(),
        )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'An assigned member cannot stop watching this task.',
            );

        $this->assertDatabaseHas(
            'task_watchers',
            [
                'task_id' =>
                    $this->taskId,

                'user_id' =>
                    $member->id,
            ],
        );
    }

    public function test_optional_watcher_can_unwatch(): void
    {
        $member =
            $this->createWorkspaceMember();

        Sanctum::actingAs($member);

        $this->postJson(
            $this->watchUrl(),
        )->assertOk();

        $this->deleteJson(
            $this->watchUrl(),
        )
            ->assertOk()
            ->assertJsonPath(
                'data.is_watching',
                false,
            )
            ->assertJsonPath(
                'data.can_comment',
                false,
            )
            ->assertJsonPath(
                'data.can_unwatch',
                false,
            );

        $this->assertDatabaseMissing(
            'task_watchers',
            [
                'task_id' =>
                    $this->taskId,

                'user_id' =>
                    $member->id,
            ],
        );
    }

    public function test_member_can_view_task_watchers(): void
    {
        $member =
            $this->createWorkspaceMember();

        Sanctum::actingAs($member);

        $this->getJson(
            $this->watchersUrl(),
        )
            ->assertOk()
            ->assertJsonPath(
                'data.is_watching',
                false,
            )
            ->assertJsonPath(
                'data.can_comment',
                false,
            )
            ->assertJsonPath(
                'data.can_unwatch',
                false,
            )
            ->assertJsonPath(
                'data.watchers.0.id',
                $this->owner->id,
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

    private function tasksUrl(): string
    {
        return "/api/workspaces/{$this->workspaceId}/projects/{$this->projectId}/tasks";
    }

    private function watchersUrl(): string
    {
        return $this->tasksUrl()
            ."/{$this->taskId}/watchers";
    }

    private function watchUrl(): string
    {
        return $this->tasksUrl()
            ."/{$this->taskId}/watch";
    }
}
