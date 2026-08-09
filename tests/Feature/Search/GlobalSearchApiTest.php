<?php

declare(strict_types=1);

namespace Tests\Feature\Search;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\WorkspaceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class GlobalSearchApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_only_receives_results_from_accessible_workspaces(): void
    {
        $user =
            User::factory()->create();

        Sanctum::actingAs($user);

        $workspaceId =
            $this->createWorkspace(
                'SearchNeedle Workspace',
            );

        $projectId =
            $this->createProject(
                $workspaceId,
                'SearchNeedle Project',
            );

        $taskId =
            $this->createTask(
                $workspaceId,
                $projectId,
                'SearchNeedle Task',
            );

        /*
         * Create a completely separate
         * workspace belonging to another user.
         */
        $otherUser =
            User::factory()->create();

        Sanctum::actingAs($otherUser);

        $otherWorkspaceId =
            $this->createWorkspace(
                'SearchNeedle Private Workspace',
            );

        $otherProjectId =
            $this->createProject(
                $otherWorkspaceId,
                'SearchNeedle Private Project',
            );

        $otherTaskId =
            $this->createTask(
                $otherWorkspaceId,
                $otherProjectId,
                'SearchNeedle Private Task',
            );

        Sanctum::actingAs($user);

        $response =
            $this->getJson(
                '/api/search?q=SearchNeedle',
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.query',
                'SearchNeedle',
            );

        $workspaceIds =
            collect(
                $response->json(
                    'data.results.workspaces',
                ),
            )->pluck('id');

        $projectIds =
            collect(
                $response->json(
                    'data.results.projects',
                ),
            )->pluck('id');

        $taskIds =
            collect(
                $response->json(
                    'data.results.tasks',
                ),
            )->pluck('id');

        $this->assertTrue(
            $workspaceIds->contains(
                $workspaceId,
            ),
        );

        $this->assertFalse(
            $workspaceIds->contains(
                $otherWorkspaceId,
            ),
        );

        $this->assertTrue(
            $projectIds->contains(
                $projectId,
            ),
        );

        $this->assertFalse(
            $projectIds->contains(
                $otherProjectId,
            ),
        );

        $this->assertTrue(
            $taskIds->contains(
                $taskId,
            ),
        );

        $this->assertFalse(
            $taskIds->contains(
                $otherTaskId,
            ),
        );
    }

    public function test_comment_search_requires_task_discussion_access(): void
    {
        $owner =
            User::factory()->create();

        Sanctum::actingAs($owner);

        $workspaceId =
            $this->createWorkspace(
                'Comment Search Workspace',
            );

        $projectId =
            $this->createProject(
                $workspaceId,
                'Comment Search Project',
            );

        $taskId =
            $this->createTask(
                $workspaceId,
                $projectId,
                'Comment Search Task',
            );

        $commentResponse =
            $this->postJson(
                $this->commentsUrl(
                    $workspaceId,
                    $projectId,
                    $taskId,
                ),
                [
                    'body' =>
                        'ConfidentialSearchNeedle comment.',
                ],
            );

        $commentResponse
            ->assertCreated();

        $commentId =
            (int) $commentResponse->json(
                'data.comment.id',
            );

        /*
         * A normal workspace member can see
         * the workspace and tasks, but does
         * not automatically have discussion
         * access.
         */
        $member =
            User::factory()->create();

        WorkspaceMembership::query()
            ->create([
                'workspace_id' =>
                    $workspaceId,

                'user_id' =>
                    $member->id,

                'role' =>
                    WorkspaceRole::Member
                        ->value,

                'joined_at' =>
                    now(),
            ]);

        Sanctum::actingAs($member);

        $beforeWatching =
            $this->getJson(
                '/api/search?q=ConfidentialSearchNeedle',
            );

        $beforeWatching
            ->assertOk();

        $commentIdsBefore =
            collect(
                $beforeWatching->json(
                    'data.results.comments',
                ),
            )->pluck('id');

        $this->assertFalse(
            $commentIdsBefore->contains(
                $commentId,
            ),
        );

        /*
         * Watching the task grants discussion
         * access according to TaskPolicy.
         */
        $this->postJson(
            $this->watchUrl(
                $workspaceId,
                $projectId,
                $taskId,
            ),
        )->assertOk();

        $afterWatching =
            $this->getJson(
                '/api/search?q=ConfidentialSearchNeedle',
            );

        $afterWatching
            ->assertOk();

        $commentIdsAfter =
            collect(
                $afterWatching->json(
                    'data.results.comments',
                ),
            )->pluck('id');

        $this->assertTrue(
            $commentIdsAfter->contains(
                $commentId,
            ),
        );
    }

    public function test_search_requires_at_least_two_characters(): void
    {
        $user =
            User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson(
            '/api/search?q=a',
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'q',
            ]);
    }

    public function test_guest_cannot_use_global_search(): void
    {
        $this->getJson(
            '/api/search?q=task',
        )->assertUnauthorized();
    }

    private function createWorkspace(
        string $name,
    ): int {
        $response =
            $this->postJson(
                '/api/workspaces',
                [
                    'name' =>
                        $name,
                ],
            );

        $response
            ->assertCreated();

        return (int) $response->json(
            'data.workspace.id',
        );
    }

    private function createProject(
        int $workspaceId,
        string $name,
    ): int {
        $response =
            $this->postJson(
                "/api/workspaces/{$workspaceId}/projects",
                [
                    'name' =>
                        $name,

                    'description' =>
                        "{$name} description.",

                    'status' =>
                        'active',
                ],
            );

        $response
            ->assertCreated();

        return (int) $response->json(
            'data.project.id',
        );
    }

    private function createTask(
        int $workspaceId,
        int $projectId,
        string $title,
    ): int {
        $response =
            $this->postJson(
                "/api/workspaces/{$workspaceId}/projects/{$projectId}/tasks",
                [
                    'title' =>
                        $title,

                    'description' =>
                        "{$title} description.",

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

        $response
            ->assertCreated();

        return (int) $response->json(
            'data.task.id',
        );
    }

    private function commentsUrl(
        int $workspaceId,
        int $projectId,
        int $taskId,
    ): string {
        return "/api/workspaces/{$workspaceId}/projects/{$projectId}/tasks/{$taskId}/comments";
    }

    private function watchUrl(
        int $workspaceId,
        int $projectId,
        int $taskId,
    ): string {
        return "/api/workspaces/{$workspaceId}/projects/{$projectId}/tasks/{$taskId}/watch";
    }
}
