<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\StoreWorkspaceMemberRequest;
use App\Http\Requests\Workspace\UpdateWorkspaceMemberRequest;
use App\Http\Resources\WorkspaceMemberResource;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class WorkspaceMemberController extends Controller
{
    /**
     * List all members belonging to the workspace.
     */
    public function index(
        Workspace $workspace
    ): JsonResponse {
        Gate::authorize(
            'view',
            $workspace
        );

        $members = $workspace
            ->memberships()
            ->with([
                'user:id,name,email,avatar_path,is_active',
            ])
            ->orderByRaw(
                "
                CASE role
                    WHEN 'owner' THEN 0
                    WHEN 'admin' THEN 1
                    ELSE 2
                END
                "
            )
            ->oldest('id')
            ->get();

        return response()->json([
            'data' => [
                'members' =>
                    WorkspaceMemberResource::collection(
                        $members
                    )->resolve(),
            ],
        ]);
    }

    /**
     * Add an existing active user to the workspace.
     */
    public function store(
        StoreWorkspaceMemberRequest $request,
        Workspace $workspace
    ): JsonResponse {
        Gate::authorize(
            'manageMembers',
            $workspace
        );

        $validated = $request->validated();

        $user = User::query()
            ->where(
                'email',
                $validated['email']
            )
            ->where('is_active', true)
            ->firstOrFail();

        $alreadyExists = $workspace
            ->memberships()
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadyExists) {
            throw ValidationException::withMessages([
                'email' => [
                    'This user is already a member of the workspace.',
                ],
            ]);
        }

        $membership = $workspace
            ->memberships()
            ->create([
                'user_id' => $user->id,

                'role' => WorkspaceRole::from(
                    $validated['role']
                ),

                'joined_at' => now(),
            ]);

        $membership->load([
            'user:id,name,email,avatar_path,is_active',
        ]);

        return response()->json([
            'message' =>
                'Workspace member added successfully.',

            'data' => [
                'member' => (
                    new WorkspaceMemberResource(
                        $membership
                    )
                )->resolve($request),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Change a workspace member role.
     */
    public function update(
        UpdateWorkspaceMemberRequest $request,
        Workspace $workspace,
        WorkspaceMembership $membership
    ): JsonResponse {
        $this->ensureMembershipBelongsToWorkspace(
            $workspace,
            $membership
        );

        Gate::authorize(
            'manageMembers',
            $workspace
        );

        $this->ensureMembershipIsNotOwner(
            $workspace,
            $membership
        );

        $validated = $request->validated();

        $membership->update([
            'role' => WorkspaceRole::from(
                $validated['role']
            ),
        ]);

        $membership->load([
            'user:id,name,email,avatar_path,is_active',
        ]);

        return response()->json([
            'message' =>
                'Workspace member role updated successfully.',

            'data' => [
                'member' => (
                    new WorkspaceMemberResource(
                        $membership
                    )
                )->resolve($request),
            ],
        ]);
    }

    /**
     * Remove a member from the workspace.
     */
    public function destroy(
        Workspace $workspace,
        WorkspaceMembership $membership
    ): JsonResponse {
        $this->ensureMembershipBelongsToWorkspace(
            $workspace,
            $membership
        );

        Gate::authorize(
            'manageMembers',
            $workspace
        );

        $this->ensureMembershipIsNotOwner(
            $workspace,
            $membership
        );

        $membership->delete();

        return response()->json([
            'message' =>
                'Workspace member removed successfully.',
        ]);
    }

    /**
     * Ensure the nested membership belongs
     * to the requested workspace.
     */
    private function ensureMembershipBelongsToWorkspace(
        Workspace $workspace,
        WorkspaceMembership $membership
    ): void {
        abort_unless(
            $membership->workspace_id ===
                $workspace->id,
            Response::HTTP_NOT_FOUND
        );
    }

    /**
     * The workspace owner membership cannot
     * be modified or removed.
     */
    private function ensureMembershipIsNotOwner(
        Workspace $workspace,
        WorkspaceMembership $membership
    ): void {
        if (
            $membership->user_id ===
                $workspace->owner_id ||
            $membership->role->isOwner()
        ) {
            throw ValidationException::withMessages([
                'membership' => [
                    'The workspace owner membership cannot be changed.',
                ],
            ]);
        }
    }
}
