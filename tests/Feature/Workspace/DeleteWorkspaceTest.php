<?php

declare(strict_types=1);

namespace Tests\Feature\Workspace;

use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskComment;
use App\Models\TaskCommentAttachment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeleteWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_delete_workspace_with_exact_name_confirmation(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
            name: 'Workspace To Delete',
            slug: 'workspace-to-delete',
        );

        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $owner->id,
            'name' => 'Workspace Project',
            'slug' => 'workspace-project',
            'status' => ProjectStatus::Active,
        ]);

        $task = Task::query()->create([
            'project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Workspace Task',
            'status' => TaskStatus::Todo,
            'priority' => TaskPriority::High,
            'position' => 1,
        ]);

        $comment = TaskComment::query()->create([
            'task_id' => $task->id,
            'user_id' => $owner->id,
            'parent_id' => null,
            'body' => 'Workspace comment.',
        ]);

        $taskAttachmentPath =
            'task-attachments/workspace-task-image.jpg';

        $commentAttachmentPath =
            'task-comment-attachments/workspace-comment-image.jpg';

        Storage::disk('local')->put(
            $taskAttachmentPath,
            'task-image-content',
        );

        Storage::disk('local')->put(
            $commentAttachmentPath,
            'comment-image-content',
        );

        $taskAttachment = TaskAttachment::query()->create([
            'task_id' => $task->id,
            'uploaded_by' => $owner->id,
            'disk' => 'local',
            'path' => $taskAttachmentPath,
            'original_name' => 'task-image.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 100,
        ]);

        $commentAttachment =
            TaskCommentAttachment::query()->create([
                'comment_id' => $comment->id,
                'uploaded_by' => $owner->id,
                'disk' => 'local',
                'path' => $commentAttachmentPath,
                'original_name' => 'comment-image.jpg',
                'mime_type' => 'image/jpeg',
                'size' => 200,
            ]);

        Storage::disk('local')->assertExists(
            $taskAttachmentPath,
        );

        Storage::disk('local')->assertExists(
            $commentAttachmentPath,
        );

        Sanctum::actingAs($owner);

        $this->deleteJson(
            "/api/workspaces/{$workspace->id}",
            [
                'confirmation_name' => 'Workspace To Delete',
            ],
        )
            ->assertOk()
            ->assertExactJson([
                'message' =>
                    'Workspace deleted successfully.',
            ]);

        $this->assertDatabaseMissing('workspaces', [
            'id' => $workspace->id,
        ]);

        $this->assertDatabaseMissing(
            'workspace_memberships',
            [
                'workspace_id' => $workspace->id,
            ],
        );

        $this->assertDatabaseMissing('projects', [
            'id' => $project->id,
        ]);

        $this->assertDatabaseMissing('tasks', [
            'id' => $task->id,
        ]);

        $this->assertDatabaseMissing('task_comments', [
            'id' => $comment->id,
        ]);

        $this->assertDatabaseMissing(
            'task_attachments',
            [
                'id' => $taskAttachment->id,
            ],
        );

        $this->assertDatabaseMissing(
            'task_comment_attachments',
            [
                'id' => $commentAttachment->id,
            ],
        );

        Storage::disk('local')->assertMissing(
            $taskAttachmentPath,
        );

        Storage::disk('local')->assertMissing(
            $commentAttachmentPath,
        );

        $this->assertDatabaseHas('users', [
            'id' => $owner->id,
        ]);
    }

    public function test_workspace_is_not_deleted_when_confirmation_name_is_wrong(): void
    {
        $owner = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
            name: 'Important Workspace',
            slug: 'important-workspace',
        );

        Sanctum::actingAs($owner);

        $this->deleteJson(
            "/api/workspaces/{$workspace->id}",
            [
                'confirmation_name' => 'Wrong Workspace',
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'confirmation_name',
            ]);

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'name' => 'Important Workspace',
        ]);

        $this->assertDatabaseHas(
            'workspace_memberships',
            [
                'workspace_id' => $workspace->id,
                'user_id' => $owner->id,
                'role' => WorkspaceRole::Owner->value,
            ],
        );
    }

    public function test_admin_cannot_delete_workspace(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
            name: 'Owner Workspace',
            slug: 'owner-workspace',
        );

        $workspace->memberships()->create([
            'user_id' => $admin->id,
            'role' => WorkspaceRole::Admin,
            'joined_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->deleteJson(
            "/api/workspaces/{$workspace->id}",
            [
                'confirmation_name' => 'Owner Workspace',
            ],
        )->assertForbidden();

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
        ]);
    }

    public function test_member_cannot_delete_workspace(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
            name: 'Protected Workspace',
            slug: 'protected-workspace',
        );

        $workspace->memberships()->create([
            'user_id' => $member->id,
            'role' => WorkspaceRole::Member,
            'joined_at' => now(),
        ]);

        Sanctum::actingAs($member);

        $this->deleteJson(
            "/api/workspaces/{$workspace->id}",
            [
                'confirmation_name' =>
                    'Protected Workspace',
            ],
        )->assertForbidden();

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
        ]);
    }

    public function test_outsider_cannot_delete_workspace(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
            name: 'Private Workspace',
            slug: 'private-workspace',
        );

        Sanctum::actingAs($outsider);

        $this->deleteJson(
            "/api/workspaces/{$workspace->id}",
            [
                'confirmation_name' => 'Private Workspace',
            ],
        )->assertForbidden();

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
        ]);
    }

    public function test_guest_cannot_delete_workspace(): void
    {
        $owner = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
            name: 'Guest Protected Workspace',
            slug: 'guest-protected-workspace',
        );

        $this->deleteJson(
            "/api/workspaces/{$workspace->id}",
            [
                'confirmation_name' =>
                    'Guest Protected Workspace',
            ],
        )->assertUnauthorized();

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
        ]);
    }

    private function createWorkspace(
        User $owner,
        string $name,
        string $slug,
    ): Workspace {
        $workspace = Workspace::query()->create([
            'owner_id' => $owner->id,
            'name' => $name,
            'slug' => $slug,
        ]);

        $workspace->memberships()->create([
            'user_id' => $owner->id,
            'role' => WorkspaceRole::Owner,
            'joined_at' => now(),
        ]);

        return $workspace;
    }
}
