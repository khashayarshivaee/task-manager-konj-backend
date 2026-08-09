<?php

declare(strict_types=1);

namespace Tests\Feature\Task;

use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskAttachmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_owner_can_upload_task_attachment(): void
    {
        Storage::fake('local');

        $owner =
            User::factory()->create();

        $workspace =
            $this->createWorkspace(
                $owner
            );

        $project =
            $this->createProject(
                $workspace,
                $owner
            );

        $task =
            $this->createTask(
                $project,
                $owner
            );

        Sanctum::actingAs(
            $owner
        );

        $image =
            UploadedFile::fake()->image(
                'task-screen.png'
            );

        $response =
            $this->post(
                $this->attachmentsUrl(
                    $workspace,
                    $project,
                    $task
                ),
                [
                    'images' => [
                        $image,
                    ],
                ],
                [
                    'Accept' =>
                        'application/json',
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Task images uploaded successfully.'
            )
            ->assertJsonPath(
                'data.attachments.0.original_name',
                'task-screen.png'
            );

        $attachmentId =
            (int) $response->json(
                'data.attachments.0.id'
            );

        $this->assertDatabaseHas(
            'task_attachments',
            [
                'id' =>
                    $attachmentId,

                'task_id' =>
                    $task->id,

                'uploaded_by' =>
                    $owner->id,

                'original_name' =>
                    'task-screen.png',
            ]
        );

        $this->assertDatabaseHas(
            'project_activities',
            [
                'project_id' =>
                    $project->id,

                'actor_id' =>
                    $owner->id,

                'type' =>
                    'task_attachment_added',

                'subject_type' =>
                    'task_attachment',

                'subject_id' =>
                    $attachmentId,

                'subject_label' =>
                    'task-screen.png',
            ]
        );
    }

    public function test_attachment_uploader_can_delete_task_attachment(): void
    {
        Storage::fake('local');

        $owner =
            User::factory()->create();

        $workspace =
            $this->createWorkspace(
                $owner
            );

        $project =
            $this->createProject(
                $workspace,
                $owner
            );

        $task =
            $this->createTask(
                $project,
                $owner
            );

        $path =
            "task-attachments/"
            ."{$workspace->id}/"
            ."{$project->id}/"
            ."{$task->id}/"
            ."delete-me.png";

        Storage::disk('local')
            ->put(
                $path,
                'fake-image-content'
            );

        $attachment =
            $task
                ->attachments()
                ->create([
                    'uploaded_by' =>
                        $owner->id,

                    'disk' =>
                        'local',

                    'path' =>
                        $path,

                    'original_name' =>
                        'delete-me.png',

                    'mime_type' =>
                        'image/png',

                    'size' =>
                        18,
                ]);

        Sanctum::actingAs(
            $owner
        );

        $this->deleteJson(
            $this->attachmentsUrl(
                $workspace,
                $project,
                $task
            )
            ."/{$attachment->id}"
        )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Task attachment deleted successfully.'
            );

        $this->assertDatabaseMissing(
            'task_attachments',
            [
                'id' =>
                    $attachment->id,
            ]
        );

        Storage::disk('local')
            ->assertMissing(
                $path
            );

        $this->assertDatabaseHas(
            'project_activities',
            [
                'project_id' =>
                    $project->id,

                'actor_id' =>
                    $owner->id,

                'type' =>
                    'task_attachment_removed',

                'subject_type' =>
                    'task_attachment',

                'subject_id' =>
                    $attachment->id,

                'subject_label' =>
                    'delete-me.png',
            ]
        );
    }

    private function createWorkspace(
        User $owner
    ): Workspace {
        $workspace =
            Workspace::query()->create([
                'owner_id' =>
                    $owner->id,

                'name' =>
                    'Attachment Workspace',

                'slug' =>
                    'attachment-workspace',
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
        User $creator
    ): Project {
        return Project::query()->create([
            'workspace_id' =>
                $workspace->id,

            'created_by' =>
                $creator->id,

            'name' =>
                'Attachment Project',

            'slug' =>
                'attachment-project',

            'status' =>
                ProjectStatus::Active,
        ]);
    }

    private function createTask(
        Project $project,
        User $creator
    ): Task {
        return Task::query()->create([
            'project_id' =>
                $project->id,

            'created_by' =>
                $creator->id,

            'title' =>
                'Attachment Task',

            'status' =>
                TaskStatus::Todo,

            'priority' =>
                TaskPriority::Medium,

            'position' =>
                1,
        ]);
    }

    private function attachmentsUrl(
        Workspace $workspace,
        Project $project,
        Task $task
    ): string {
        return "/api/workspaces/"
            ."{$workspace->id}"
            ."/projects/"
            ."{$project->id}"
            ."/tasks/"
            ."{$task->id}"
            ."/attachments";
    }
}
