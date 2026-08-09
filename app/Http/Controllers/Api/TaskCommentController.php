<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;
use App\Services\TaskCommentRealtimeService;
use Illuminate\Support\Facades\Gate;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskCommentRequest;
use App\Http\Requests\UpdateTaskCommentRequest;
use App\Http\Resources\TaskCommentResource;
use App\Models\Project;
use App\Models\Task;
use App\Services\TaskDiscussionReadService;
use App\Models\TaskComment;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use App\Enums\ProjectActivitySubjectType;
use App\Enums\ProjectActivityType;
use App\Services\ProjectActivityLogger;

class TaskCommentController extends Controller
{
    public function __construct(
        private readonly ProjectActivityLogger $activityLogger,
    ) {
    }
    public function index(
        Workspace $workspace,
        Project $project,
        Task $task,
    ): JsonResponse {
        $user = request()->user();

        abort_unless(
            $user instanceof User,
            401,
        );

        $this->assertTaskContext(
            $workspace,
            $project,
            $task,
            $user,
        );
        Gate::authorize(
            'participateInDiscussion',
            $task,
        );

        $comments =
            TaskComment::query()
                ->withTrashed()
                ->where(
                    'task_id',
                    $task->id,
                )
                ->whereNull('parent_id')
                ->with([
                    'user:id,name,avatar_path',

                    'attachments' => fn (
                        $query,
                    ) => $query
                        ->oldest('id')
                        ->with(
                            'uploader:id,name',
                        ),

                    'repliesRecursive',
                ])
                ->oldest('id')
                ->get();

        return response()->json([
            'data' => [
                'comments' =>
                    TaskCommentResource::collection(
                        $comments,
                    ),
            ],
        ]);
    }

public function store(
    StoreTaskCommentRequest $request,
    Workspace $workspace,
    Project $project,
    Task $task,
    TaskDiscussionReadService $discussionRead,
    TaskCommentRealtimeService $realtime,
): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
        );

        $this->assertTaskContext(
            $workspace,
            $project,
            $task,
            $user,
        );
        Gate::authorize(
            'participateInDiscussion',
            $task,
        );

        $validated =
            $request->validated();

        $parentId = $this->resolveParentId(
            $task,
            $validated['parent_id'] ?? null,
        );

        /** @var list<UploadedFile> $images */
        $images = $request->file(
            'images',
            [],
        );

        $storedFiles = [];

        try {
            $comment = DB::transaction(
                function () use (
                    $task,
                    $user,
                    $validated,
                    $parentId,
                    $images,
                    $discussionRead,
                    &$storedFiles,
                ): TaskComment {

                    $comment =
                        TaskComment::query()
                            ->create([
                                'task_id' =>
                                    $task->id,

                                'user_id' =>
                                    $user->id,

                                'parent_id' =>
                                    $parentId,

                                'body' =>
                                    $validated[
                                        'body'
                                    ] ?? null,
                            ]);

                    foreach (
                        $images as $image
                    ) {
                        $extension =
                            strtolower(
                                $image
                                    ->guessExtension()
                                ?: 'jpg',
                            );

                        $fileName =
                            Str::uuid()
                                ->toString()
                            .'.'
                            .$extension;

                        $directory =
                            'task-comments/'
                            .$task->id
                            .'/'
                            .$comment->id;

                        $path =
                            $image->storeAs(
                                $directory,
                                $fileName,
                                'local',
                            );

                        if (!is_string($path)) {
                            throw new RuntimeException(
                                'Unable to store comment image.',
                            );
                        }

                        $storedFiles[] = $path;

                        $comment
                            ->attachments()
                            ->create([
                                'uploaded_by' =>
                                    $user->id,

                                'disk' =>
                                    'local',

                                'path' =>
                                    $path,

                                'original_name' =>
                                    $image
                                        ->getClientOriginalName(),

                                'mime_type' =>
                                    $image
                                        ->getMimeType()
                                    ?: 'application/octet-stream',

                                'size' =>
                                    $image
                                        ->getSize(),
                            ]);
                    }
                    $discussionRead
                        ->ensureWatchingAtLatest(
                            $task,
                            $user,
                        );

                    $discussionRead
                        ->markReadThrough(
                            $task,
                            $user,
                            $comment->id,
                        );

                    return $comment;
                },
            );
        } catch (Throwable $exception) {
            foreach (
                $storedFiles as $path
            ) {
                Storage::disk('local')
                    ->delete($path);
            }

            throw $exception;
        }

        $comment->load([
            'user:id,name,avatar_path',

            'attachments' => fn (
                $query,
            ) => $query
                ->oldest('id')
                ->with(
                    'uploader:id,name',
                ),

            'repliesRecursive',
        ]);
        $this->activityLogger->log(
            project: $project,
            type:
                ProjectActivityType::CommentCreated,
            actor: $user,
            subjectType:
                ProjectActivitySubjectType::Comment,
            subjectId: $comment->id,
            subjectLabel:
                $this->commentActivityLabel(
                    $comment
                ),
            metadata: [
                'task_id' =>
                    $task->id,

                'parent_id' =>
                    $comment->parent_id,

                'body' =>
                    $comment->body,

                'attachments_count' =>
                    $comment->attachments->count(),
            ],
        );
        $commentPayload =
            (new TaskCommentResource(
                $comment,
            ))->resolve(
                $request,
            );

        $realtime->broadcastCreated(
            (int) $workspace->id,
            (int) $project->id,
            $task,
            (int) $user->id,
            $commentPayload,
        );

        return response()->json(
            [
                'message' =>
                    'Comment created successfully.',

                'data' => [
                    'comment' =>
                        new TaskCommentResource(
                            $comment,
                        ),
                ],
            ],
            201,
        );
    }

  public function update(
      UpdateTaskCommentRequest $request,
      Workspace $workspace,
      Project $project,
      Task $task,
      TaskComment $comment,
      TaskCommentRealtimeService $realtime,
  ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
        );

        $this->assertCommentContext(
            $workspace,
            $project,
            $task,
            $comment,
            $user,
        );
        Gate::authorize(
            'participateInDiscussion',
            $task,
        );

        $this->assertCanManageComment(
            $workspace,
            $comment,
            $user,
        );
        $previousBody =
            $comment->body;

        $body = $request->validated(
            'body',
        );

        if (
            $body === null &&
            !$comment
                ->attachments()
                ->exists()
        ) {
            return response()->json(
                [
                    'message' =>
                        'A comment must contain text or at least one image.',

                    'errors' => [
                        'body' => [
                            'A comment must contain text or at least one image.',
                        ],
                    ],
                ],
                422,
            );
        }

        $comment->forceFill([
            'body' => $body,
            'edited_at' => now(),
        ])->save();

        if (
            $previousBody !==
            $comment->body
        ) {
            $this->activityLogger->log(
                project: $project,
                type:
                    ProjectActivityType::CommentUpdated,
                actor: $user,
                subjectType:
                    ProjectActivitySubjectType::Comment,
                subjectId: $comment->id,
                subjectLabel:
                    $this->commentActivityLabel(
                        $comment
                    ),
                metadata: [
                    'task_id' =>
                        $task->id,

                    'parent_id' =>
                        $comment->parent_id,

                    'changes' => [
                        'body' => [
                            'from' =>
                                $previousBody,

                            'to' =>
                                $comment->body,
                        ],
                    ],
                ],
            );
        }

        $comment->load([
            'user:id,name,avatar_path',

            'attachments' => fn (
                $query,
            ) => $query
                ->oldest('id')
                ->with(
                    'uploader:id,name',
                ),

            'repliesRecursive',
        ]);
        $commentPayload =
            (new TaskCommentResource(
                $comment,
            ))->resolve(
                $request,
            );

        $realtime->broadcastUpdated(
            (int) $workspace->id,
            (int) $project->id,
            $task,
            (int) $user->id,
            $commentPayload,
        );

        return response()->json([
            'message' =>
                'Comment updated successfully.',

            'data' => [
                'comment' =>
                    new TaskCommentResource(
                        $comment,
                    ),
            ],
        ]);
    }

   public function destroy(
       Workspace $workspace,
       Project $project,
       Task $task,
       TaskComment $comment,
       TaskCommentRealtimeService $realtime,
   ): JsonResponse {
        $user = request()->user();

        abort_unless(
            $user instanceof User,
            401,
        );

        $this->assertCommentContext(
            $workspace,
            $project,
            $task,
            $comment,
            $user,
        );
        Gate::authorize(
            'participateInDiscussion',
            $task,
        );

        $this->assertCanManageComment(
            $workspace,
            $comment,
            $user,
        );
        $commentId =
            (int) $comment->id;

        $parentId =
            $comment->parent_id === null
                ? null
                : (int) $comment->parent_id;

                $commentBody =
                    $comment->body;

                $commentLabel =
                    $this->commentActivityLabel(
                        $comment
                    );

        $attachments =
            $comment
                ->attachments()
                ->get();

        DB::transaction(
            function () use (
                $comment,
                $attachments,
            ): void {
                foreach (
                    $attachments as $attachment
                ) {
                    Storage::disk(
                        $attachment->disk,
                    )->delete(
                        $attachment->path,
                    );

                    $attachment->delete();
                }

                $comment->delete();
            },
        );
        $this->activityLogger->log(
            project: $project,
            type:
                ProjectActivityType::CommentDeleted,
            actor: $user,
            subjectType:
                ProjectActivitySubjectType::Comment,
            subjectId: $commentId,
            subjectLabel:
                $commentLabel,
            metadata: [
                'task_id' =>
                    $task->id,

                'parent_id' =>
                    $parentId,

                'body' =>
                    $commentBody,
            ],
        );
        $realtime->broadcastDeleted(
            (int) $workspace->id,
            (int) $project->id,
            $task,
            (int) $user->id,
            $commentId,
            $parentId,
        );

        return response()->json([
            'message' =>
                'Comment deleted successfully.',
        ]);
    }
    private function commentActivityLabel(
        TaskComment $comment,
    ): string {
        $body = trim(
            (string) $comment->body
        );

        if ($body === '') {
            return 'Image comment';
        }

        return Str::limit(
            $body,
            120
        );
    }

    private function resolveParentId(
        Task $task,
        mixed $parentId,
    ): ?int {
        if (!$parentId) {
            return null;
        }

        $parent =
            TaskComment::query()
                ->whereKey($parentId)
                ->where(
                    'task_id',
                    $task->id,
                )
                ->firstOrFail();

        return $parent->parent_id
            ?: $parent->id;
    }

    private function assertTaskContext(
        Workspace $workspace,
        Project $project,
        Task $task,
        User $user,
    ): void {
        abort_if(
            $project->workspace_id
                !== $workspace->id,
            404,
        );

        abort_if(
            $task->project_id
                !== $project->id,
            404,
        );

        abort_unless(
            $this->isWorkspaceMember(
                $workspace,
                $user,
            ),
            403,
        );
    }

    private function assertCommentContext(
        Workspace $workspace,
        Project $project,
        Task $task,
        TaskComment $comment,
        User $user,
    ): void {
        $this->assertTaskContext(
            $workspace,
            $project,
            $task,
            $user,
        );

        abort_if(
            $comment->task_id !== $task->id,
            404,
        );
    }

    private function assertCanManageComment(
        Workspace $workspace,
        TaskComment $comment,
        User $user,
    ): void {
        abort_unless(
            $comment->user_id === $user->id
            || $this->isWorkspaceManager(
                $workspace,
                $user,
            ),
            403,
        );
    }

    private function isWorkspaceMember(
        Workspace $workspace,
        User $user,
    ): bool {
        if (
            $workspace->owner_id ===
            $user->id
        ) {
            return true;
        }

        return WorkspaceMembership::query()
            ->where(
                'workspace_id',
                $workspace->id,
            )
            ->where(
                'user_id',
                $user->id,
            )
            ->exists();
    }

    private function isWorkspaceManager(
        Workspace $workspace,
        User $user,
    ): bool {
        if (
            $workspace->owner_id ===
            $user->id
        ) {
            return true;
        }

        return WorkspaceMembership::query()
            ->where(
                'workspace_id',
                $workspace->id,
            )
            ->where(
                'user_id',
                $user->id,
            )
            ->whereIn(
                'role',
                [
                    WorkspaceRole::Owner
                        ->value,

                    WorkspaceRole::Admin
                        ->value,
                ],
            )
            ->exists();
    }
}
