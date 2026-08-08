<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectMemberRequest;
use App\Http\Resources\ProjectMemberResource;
use App\Models\Project;
use App\Models\ProjectMembership;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ProjectMemberController extends Controller
{
    /**
     * List all members belonging to the project.
     */
    public function index(
        Workspace $workspace,
        Project $project
    ): JsonResponse {
        $this->ensureProjectBelongsToWorkspace(
            $workspace,
            $project
        );

        Gate::authorize(
            'view',
            $workspace
        );

        $members = $project
            ->memberships()
            ->with([
                'user:id,name,email,avatar_path,is_active',
                'addedBy:id,name,email',
            ])
            ->orderByRaw(
                'CASE
                    WHEN user_id = ? THEN 0
                    ELSE 1
                END',
                [
                    $project->created_by,
                ]
            )
            ->oldest('joined_at')
            ->oldest('id')
            ->get();

        return response()->json([
            'data' => [
                'members' =>
                    ProjectMemberResource::collection(
                        $members
                    )->resolve(),
            ],
        ]);
    }

    /**
     * Add an existing workspace member
     * to the project team.
     */
    public function store(
        StoreProjectMemberRequest $request,
        Workspace $workspace,
        Project $project
    ): JsonResponse {
        $this->ensureProjectBelongsToWorkspace(
            $workspace,
            $project
        );

        Gate::authorize(
            'manageProjects',
            $workspace
        );

        $validated = $request->validated();

        $membership = $project
            ->memberships()
            ->create([
                'user_id' =>
                    $validated['user_id'],

                'added_by' =>
                    $request->user()->id,

                'joined_at' => now(),
            ]);

        $membership->load([
            'user:id,name,email,avatar_path,is_active',
            'addedBy:id,name,email',
        ]);

        return response()->json([
            'message' =>
                'Project member added successfully.',

            'data' => [
                'member' => (
                    new ProjectMemberResource(
                        $membership
                    )
                )->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Remove a member from the project team.
     */
    public function destroy(
        Workspace $workspace,
        Project $project,
        ProjectMembership $membership
    ): JsonResponse {
        $this->ensureProjectBelongsToWorkspace(
            $workspace,
            $project
        );

        $this->ensureMembershipBelongsToProject(
            $project,
            $membership
        );

        Gate::authorize(
            'manageProjects',
            $workspace
        );

   $this->ensureMembershipIsNotCreator(
       $project,
       $membership
   );

   $this->ensureMembershipHasNoTaskAssignments(
       $project,
       $membership
   );

   $membership->delete();

        return response()->json([
            'message' =>
                'Project member removed successfully.',
        ]);
    }

    /**
     * Ensure that the nested project
     * belongs to the requested workspace.
     */
    private function ensureProjectBelongsToWorkspace(
        Workspace $workspace,
        Project $project
    ): void {
        abort_unless(
            $project->workspace_id ===
                $workspace->id,
            Response::HTTP_NOT_FOUND
        );
    }

    /**
     * Ensure that the nested membership
     * belongs to the requested project.
     */
    private function ensureMembershipBelongsToProject(
        Project $project,
        ProjectMembership $membership
    ): void {
        abort_unless(
            $membership->project_id ===
                $project->id,
            Response::HTTP_NOT_FOUND
        );
    }

    /**
     * The project creator must remain
     * a member of the project team.
     */
    private function ensureMembershipIsNotCreator(
        Project $project,
        ProjectMembership $membership
    ): void {
        if (
            $project->created_by !== null &&
            $membership->user_id ===
                $project->created_by
        ) {
            throw ValidationException::withMessages([
                'membership' => [
                    'The project creator cannot be removed from the project team.',
                ],
            ]);
        }
    }
    /**
     * A project member cannot be removed while
     * they are still assigned to tasks
     * inside this project.
     */
    private function ensureMembershipHasNoTaskAssignments(
        Project $project,
        ProjectMembership $membership
    ): void {
        $hasAssignments = DB::table(
            'task_assignees'
        )
            ->join(
                'tasks',
                'tasks.id',
                '=',
                'task_assignees.task_id'
            )
            ->where(
                'tasks.project_id',
                $project->id
            )
            ->where(
                'task_assignees.user_id',
                $membership->user_id
            )
            ->exists();

        if ($hasAssignments) {
            throw ValidationException::withMessages([
                'membership' => [
                    'This project member cannot be removed while assigned to project tasks.',
                ],
            ]);
        }
    }
}
