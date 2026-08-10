<?php

declare(strict_types=1);

namespace Tests\Feature\Workspace;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Models\WorkspaceNote;
use App\Models\WorkspaceNoteAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkspaceNoteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_member_can_create_and_view_note(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
        );

        $this->addMembership(
            workspace: $workspace,
            user: $member,
            role: WorkspaceRole::Member,
        );

        Sanctum::actingAs($member);

        $response = $this->postJson(
            "/api/workspaces/{$workspace->id}/notes",
            [
                'title' => '  Team Meeting  ',
                'content' => 'Meeting notes.',
                'is_pinned' => true,
            ],
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Note created successfully.',
            )
            ->assertJsonPath(
                'data.note.title',
                'Team Meeting',
            )
            ->assertJsonPath(
                'data.note.content',
                'Meeting notes.',
            )
            ->assertJsonPath(
                'data.note.is_pinned',
                true,
            )
            ->assertJsonPath(
                'data.note.author.id',
                $member->id,
            );

        $note = WorkspaceNote::query()
            ->firstOrFail();

        $this->assertDatabaseHas(
            'workspace_notes',
            [
                'id' => $note->id,
                'workspace_id' =>
                    $workspace->id,
                'author_id' =>
                    $member->id,
                'title' =>
                    'Team Meeting',
                'is_pinned' => true,
            ],
        );

        $this->getJson(
            "/api/workspaces/{$workspace->id}/notes/{$note->id}",
        )
            ->assertOk()
            ->assertJsonPath(
                'data.note.id',
                $note->id,
            );
    }

    public function test_notes_support_search_and_pinned_notes_come_first(): void
    {
        $owner = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
        );

        $regularNote = $this->createNote(
            workspace: $workspace,
            author: $owner,
            title: 'Backend deployment',
            content: 'Production checklist',
        );

        $pinnedNote = $this->createNote(
            workspace: $workspace,
            author: $owner,
            title: 'Design meeting',
            content: 'Dashboard redesign',
            isPinned: true,
        );

        Sanctum::actingAs($owner);

        $this->getJson(
            "/api/workspaces/{$workspace->id}/notes",
        )
            ->assertOk()
            ->assertJsonPath(
                'data.notes.0.id',
                $pinnedNote->id,
            )
            ->assertJsonPath(
                'data.notes.1.id',
                $regularNote->id,
            );

        $this->getJson(
            "/api/workspaces/{$workspace->id}/notes?search=production",
        )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.notes',
            )
            ->assertJsonPath(
                'data.notes.0.id',
                $regularNote->id,
            );
    }

    public function test_member_can_edit_own_note(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
        );

        $this->addMembership(
            workspace: $workspace,
            user: $member,
            role: WorkspaceRole::Member,
        );

        $note = $this->createNote(
            workspace: $workspace,
            author: $member,
            title: 'Original title',
            content: 'Original content',
        );

        Sanctum::actingAs($member);

        $this->putJson(
            "/api/workspaces/{$workspace->id}/notes/{$note->id}",
            [
                'title' =>
                    'Updated title',

                'content' =>
                    'Updated content',

                'is_pinned' => true,
            ],
        )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Note updated successfully.',
            )
            ->assertJsonPath(
                'data.note.title',
                'Updated title',
            )
            ->assertJsonPath(
                'data.note.content',
                'Updated content',
            )
            ->assertJsonPath(
                'data.note.is_pinned',
                true,
            );

        $this->assertDatabaseHas(
            'workspace_notes',
            [
                'id' => $note->id,
                'title' => 'Updated title',
                'content' =>
                    'Updated content',
                'is_pinned' => true,
            ],
        );
    }

    public function test_member_cannot_edit_or_delete_another_members_note(): void
    {
        $owner = User::factory()->create();

        $author = User::factory()->create();
        $member = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
        );

        $this->addMembership(
            workspace: $workspace,
            user: $author,
            role: WorkspaceRole::Member,
        );

        $this->addMembership(
            workspace: $workspace,
            user: $member,
            role: WorkspaceRole::Member,
        );

        $note = $this->createNote(
            workspace: $workspace,
            author: $author,
            title: 'Private ownership',
        );

        Sanctum::actingAs($member);

        $this->putJson(
            "/api/workspaces/{$workspace->id}/notes/{$note->id}",
            [
                'title' => 'Unauthorized edit',
            ],
        )->assertForbidden();

        $this->deleteJson(
            "/api/workspaces/{$workspace->id}/notes/{$note->id}",
        )->assertForbidden();

        $this->assertDatabaseHas(
            'workspace_notes',
            [
                'id' => $note->id,
                'title' =>
                    'Private ownership',
            ],
        );
    }

    public function test_admin_can_edit_and_delete_another_members_note(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
        );

        $this->addMembership(
            workspace: $workspace,
            user: $admin,
            role: WorkspaceRole::Admin,
        );

        $this->addMembership(
            workspace: $workspace,
            user: $member,
            role: WorkspaceRole::Member,
        );

        $note = $this->createNote(
            workspace: $workspace,
            author: $member,
            title: 'Member note',
        );

        Sanctum::actingAs($admin);

        $this->putJson(
            "/api/workspaces/{$workspace->id}/notes/{$note->id}",
            [
                'title' =>
                    'Edited by admin',
            ],
        )
            ->assertOk()
            ->assertJsonPath(
                'data.note.title',
                'Edited by admin',
            );

        $this->deleteJson(
            "/api/workspaces/{$workspace->id}/notes/{$note->id}",
        )->assertOk();

        $this->assertDatabaseMissing(
            'workspace_notes',
            [
                'id' => $note->id,
            ],
        );
    }

    public function test_owner_can_manage_any_workspace_note(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
        );

        $this->addMembership(
            workspace: $workspace,
            user: $member,
            role: WorkspaceRole::Member,
        );

        $note = $this->createNote(
            workspace: $workspace,
            author: $member,
            title: 'Member note',
        );

        Sanctum::actingAs($owner);

        $this->putJson(
            "/api/workspaces/{$workspace->id}/notes/{$note->id}",
            [
                'title' => 'Owner edited',
            ],
        )->assertOk();

        $this->deleteJson(
            "/api/workspaces/{$workspace->id}/notes/{$note->id}",
        )->assertOk();

        $this->assertDatabaseMissing(
            'workspace_notes',
            [
                'id' => $note->id,
            ],
        );
    }

    public function test_outsider_cannot_access_workspace_notes(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
        );

        $note = $this->createNote(
            workspace: $workspace,
            author: $owner,
            title: 'Workspace note',
        );

        Sanctum::actingAs($outsider);

        $this->getJson(
            "/api/workspaces/{$workspace->id}/notes",
        )->assertForbidden();

        $this->postJson(
            "/api/workspaces/{$workspace->id}/notes",
            [
                'title' => 'Unauthorized',
            ],
        )->assertForbidden();

        $this->getJson(
            "/api/workspaces/{$workspace->id}/notes/{$note->id}",
        )->assertForbidden();
    }

    public function test_removed_member_cannot_edit_own_old_note(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
        );

        $membership = $this->addMembership(
            workspace: $workspace,
            user: $member,
            role: WorkspaceRole::Member,
        );

        $note = $this->createNote(
            workspace: $workspace,
            author: $member,
            title: 'Old member note',
        );

        $membership->delete();

        Sanctum::actingAs($member);

        $this->putJson(
            "/api/workspaces/{$workspace->id}/notes/{$note->id}",
            [
                'title' => 'Should fail',
            ],
        )->assertForbidden();

        $this->deleteJson(
            "/api/workspaces/{$workspace->id}/notes/{$note->id}",
        )->assertForbidden();
    }

    public function test_note_cannot_be_accessed_through_another_workspace(): void
    {
        $firstOwner =
            User::factory()->create();

        $secondOwner =
            User::factory()->create();

        $firstWorkspace =
            $this->createWorkspace(
                owner: $firstOwner,
                name: 'First Workspace',
                slug: 'first-workspace',
            );

        $secondWorkspace =
            $this->createWorkspace(
                owner: $secondOwner,
                name: 'Second Workspace',
                slug: 'second-workspace',
            );

        $note = $this->createNote(
            workspace: $secondWorkspace,
            author: $secondOwner,
            title: 'Second workspace note',
        );

        Sanctum::actingAs($firstOwner);

        $this->getJson(
            "/api/workspaces/{$firstWorkspace->id}/notes/{$note->id}",
        )->assertNotFound();

        $this->putJson(
            "/api/workspaces/{$firstWorkspace->id}/notes/{$note->id}",
            [
                'title' => 'Wrong workspace',
            ],
        )->assertNotFound();

        $this->deleteJson(
            "/api/workspaces/{$firstWorkspace->id}/notes/{$note->id}",
        )->assertNotFound();
    }

    public function test_note_author_can_upload_multiple_private_images(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $member = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
        );

        $this->addMembership(
            workspace: $workspace,
            user: $member,
            role: WorkspaceRole::Member,
        );

        $note = $this->createNote(
            workspace: $workspace,
            author: $member,
            title: 'Image note',
        );

        Sanctum::actingAs($member);

        $response = $this->post(
            "/api/workspaces/{$workspace->id}/notes/{$note->id}/attachments",
            [
                'images' => [
                    UploadedFile::fake()
                        ->image('first.jpg'),

                    UploadedFile::fake()
                        ->image('second.png'),
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
                'message',
                'Note images uploaded successfully.',
            )
            ->assertJsonCount(
                2,
                'data.attachments',
            );

        $attachments =
            WorkspaceNoteAttachment::query()
                ->where(
                    'note_id',
                    $note->id,
                )
                ->get();

        $this->assertCount(
            2,
            $attachments,
        );

        foreach (
            $attachments as $attachment
        ) {
            Storage::disk(
                $attachment->disk,
            )->assertExists(
                $attachment->path,
            );
        }

        $firstPayload =
            $response->json(
                'data.attachments.0'
            );

        $this->assertIsArray(
            $firstPayload
        );

        $this->assertArrayNotHasKey(
            'disk',
            $firstPayload,
        );

        $this->assertArrayNotHasKey(
            'path',
            $firstPayload,
        );
    }

    public function test_member_cannot_upload_image_to_another_members_note(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $author = User::factory()->create();
        $member = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
        );

        $this->addMembership(
            workspace: $workspace,
            user: $author,
            role: WorkspaceRole::Member,
        );

        $this->addMembership(
            workspace: $workspace,
            user: $member,
            role: WorkspaceRole::Member,
        );

        $note = $this->createNote(
            workspace: $workspace,
            author: $author,
            title: 'Author note',
        );

        Sanctum::actingAs($member);

        $this->post(
            "/api/workspaces/{$workspace->id}/notes/{$note->id}/attachments",
            [
                'images' => [
                    UploadedFile::fake()
                        ->image('blocked.jpg'),
                ],
            ],
            [
                'Accept' =>
                    'application/json',
            ],
        )->assertForbidden();

        $this->assertDatabaseCount(
            'workspace_note_attachments',
            0,
        );
    }

    public function test_note_cannot_contain_more_than_ten_images(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
        );

        $note = $this->createNote(
            workspace: $workspace,
            author: $owner,
            title: 'Gallery',
        );

        for ($index = 1; $index <= 10; $index++) {
            $note
                ->attachments()
                ->create([
                    'uploaded_by' =>
                        $owner->id,

                    'disk' => 'local',

                    'path' =>
                        "existing/{$index}.jpg",

                    'original_name' =>
                        "{$index}.jpg",

                    'mime_type' =>
                        'image/jpeg',

                    'size' => 100,
                ]);
        }

        Sanctum::actingAs($owner);

        $this->post(
            "/api/workspaces/{$workspace->id}/notes/{$note->id}/attachments",
            [
                'images' => [
                    UploadedFile::fake()
                        ->image('eleventh.jpg'),
                ],
            ],
            [
                'Accept' =>
                    'application/json',
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'images',
            ]);

        $this->assertDatabaseCount(
            'workspace_note_attachments',
            10,
        );
    }

    public function test_workspace_member_can_view_private_note_image_but_outsider_cannot(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $member = User::factory()->create();
        $viewer = User::factory()->create();
        $outsider = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
        );

        $this->addMembership(
            workspace: $workspace,
            user: $member,
            role: WorkspaceRole::Member,
        );

        $this->addMembership(
            workspace: $workspace,
            user: $viewer,
            role: WorkspaceRole::Member,
        );

        $note = $this->createNote(
            workspace: $workspace,
            author: $member,
            title: 'Private image',
        );

        Sanctum::actingAs($member);

        $this->post(
            "/api/workspaces/{$workspace->id}/notes/{$note->id}/attachments",
            [
                'images' => [
                    UploadedFile::fake()
                        ->image('private.jpg'),
                ],
            ],
            [
                'Accept' =>
                    'application/json',
            ],
        )->assertCreated();

        $attachment =
            WorkspaceNoteAttachment::query()
                ->firstOrFail();

        Sanctum::actingAs($viewer);

        $this->get(
            "/api/workspaces/{$workspace->id}/notes/{$note->id}/attachments/{$attachment->id}/file",
        )->assertOk();

        Sanctum::actingAs($outsider);

        $this->get(
            "/api/workspaces/{$workspace->id}/notes/{$note->id}/attachments/{$attachment->id}/file",
        )->assertForbidden();
    }

    public function test_deleting_attachment_removes_private_file(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
        );

        $note = $this->createNote(
            workspace: $workspace,
            author: $owner,
            title: 'Delete image',
        );

        Sanctum::actingAs($owner);

        $this->post(
            "/api/workspaces/{$workspace->id}/notes/{$note->id}/attachments",
            [
                'images' => [
                    UploadedFile::fake()
                        ->image('delete-me.jpg'),
                ],
            ],
            [
                'Accept' =>
                    'application/json',
            ],
        )->assertCreated();

        $attachment =
            WorkspaceNoteAttachment::query()
                ->firstOrFail();

        $disk = $attachment->disk;
        $path = $attachment->path;

        Storage::disk($disk)
            ->assertExists($path);

        $this->deleteJson(
            "/api/workspaces/{$workspace->id}/notes/{$note->id}/attachments/{$attachment->id}",
        )->assertOk();

        Storage::disk($disk)
            ->assertMissing($path);

        $this->assertDatabaseMissing(
            'workspace_note_attachments',
            [
                'id' =>
                    $attachment->id,
            ],
        );
    }

    public function test_deleting_note_removes_all_private_image_files(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();

        $workspace = $this->createWorkspace(
            owner: $owner,
        );

        $note = $this->createNote(
            workspace: $workspace,
            author: $owner,
            title: 'Delete full note',
        );

        Sanctum::actingAs($owner);

        $this->post(
            "/api/workspaces/{$workspace->id}/notes/{$note->id}/attachments",
            [
                'images' => [
                    UploadedFile::fake()
                        ->image('one.jpg'),

                    UploadedFile::fake()
                        ->image('two.jpg'),
                ],
            ],
            [
                'Accept' =>
                    'application/json',
            ],
        )->assertCreated();

        $attachments =
            WorkspaceNoteAttachment::query()
                ->where(
                    'note_id',
                    $note->id,
                )
                ->get();

        $this->assertCount(
            2,
            $attachments,
        );

        foreach (
            $attachments as $attachment
        ) {
            Storage::disk(
                $attachment->disk,
            )->assertExists(
                $attachment->path,
            );
        }

        $this->deleteJson(
            "/api/workspaces/{$workspace->id}/notes/{$note->id}",
        )->assertOk();

        foreach (
            $attachments as $attachment
        ) {
            Storage::disk(
                $attachment->disk,
            )->assertMissing(
                $attachment->path,
            );
        }

        $this->assertDatabaseMissing(
            'workspace_notes',
            [
                'id' => $note->id,
            ],
        );

        $this->assertDatabaseCount(
            'workspace_note_attachments',
            0,
        );
    }

    private function createWorkspace(
        User $owner,
        string $name = 'Test Workspace',
        string $slug = 'test-workspace',
    ): Workspace {
        $workspace =
            Workspace::query()->create([
                'owner_id' =>
                    $owner->id,

                'name' => $name,
                'slug' => $slug,
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

    private function addMembership(
        Workspace $workspace,
        User $user,
        WorkspaceRole $role,
    ): WorkspaceMembership {
        return $workspace
            ->memberships()
            ->create([
                'user_id' =>
                    $user->id,

                'role' => $role,

                'joined_at' =>
                    now(),
            ]);
    }

    private function createNote(
        Workspace $workspace,
        User $author,
        string $title,
        ?string $content = null,
        bool $isPinned = false,
    ): WorkspaceNote {
        return $workspace
            ->notes()
            ->create([
                'author_id' =>
                    $author->id,

                'title' => $title,

                'content' =>
                    $content,

                'is_pinned' =>
                    $isPinned,
            ]);
    }
}
