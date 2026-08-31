<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\InstagramAccessGrant;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InstagramAccessController extends Controller
{
    /**
     * List users and their Instagram access
     * status for the workspace.
     */
    public function index(
        Workspace $workspace
    ): JsonResponse {
        Gate::authorize(
            'manageInstagramAccess',
            $workspace
        );

        $grantedUserIds = $workspace
            ->instagramAccessGrants()
            ->pluck('user_id');

        $users = User::query()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'role',
                'is_active',
            ])
            ->map(function (User $user) use ($grantedUserIds): array {
                $isAdmin = $user->isAdmin();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_active' => $user->isActive(),
                    'is_admin' => $isAdmin,

                    /*
                     * System admins always have access.
                     * Regular users need an explicit grant.
                     */
                    'instagram_access' => $isAdmin
                        || $grantedUserIds->contains($user->id),

                    /*
                     * There is no need to toggle access
                     * for system administrators.
                     */
                    'can_manage_access' => !$isAdmin,
                ];
            })
            ->values();

        return response()->json([
            'workspace_id' => $workspace->id,
            'users' => $users,
        ]);
    }

    /**
     * Grant Instagram access to a user.
     */
    public function grant(
        Request $request,
        Workspace $workspace,
        User $user
    ): JsonResponse {
        Gate::authorize(
            'manageInstagramAccess',
            $workspace
        );

        if ($user->isAdmin()) {
            return response()->json([
                'message' => 'System administrators already have Instagram access.',
            ], 422);
        }

        InstagramAccessGrant::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
            ],
            [
                'granted_by_user_id' => $request->user()->id,
            ]
        );

        return response()->json([
            'message' => 'Instagram access granted successfully.',
            'user_id' => $user->id,
            'instagram_access' => true,
        ]);
    }

    /**
     * Revoke Instagram access from a user.
     */
    public function revoke(
        Workspace $workspace,
        User $user
    ): JsonResponse {
        Gate::authorize(
            'manageInstagramAccess',
            $workspace
        );

        if ($user->isAdmin()) {
            return response()->json([
                'message' => 'System administrator Instagram access cannot be revoked.',
            ], 422);
        }

        InstagramAccessGrant::query()
            ->where(
                'workspace_id',
                $workspace->id
            )
            ->where(
                'user_id',
                $user->id
            )
            ->delete();

        return response()->json([
            'message' => 'Instagram access revoked successfully.',
            'user_id' => $user->id,
            'instagram_access' => false,
        ]);
    }
}
