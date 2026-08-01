<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexUsersRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    /**
     * دریافت فهرست صفحه‌بندی‌شده کاربران.
     */
    public function index(IndexUsersRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = User::query()
            ->when(
                $validated['search'] ?? null,
                function (Builder $query, string $search): void {
                    $query->where(function (Builder $query) use ($search): void {
                        $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                }
            )
            ->when(
                $validated['role'] ?? null,
                fn (Builder $query, string $role): Builder => $query->where(
                    'role',
                    $role
                )
            )
            ->when(
                array_key_exists('is_active', $validated),
                fn (Builder $query): Builder => $query->where(
                    'is_active',
                    $validated['is_active']
                )
            );

        match ($validated['sort'] ?? 'latest') {
            'oldest' => $query->oldest('id'),
            'name_asc' => $query
                ->orderBy('name')
                ->orderBy('id'),
            'name_desc' => $query
                ->orderByDesc('name')
                ->orderByDesc('id'),
            default => $query->latest('id'),
        };

        $users = $query->paginate(
            $validated['per_page'] ?? 20
        );

        return response()->json([
            'data' => [
                'users' => $users->items(),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                ],
            ],
        ]);
    }

    /**
     * دریافت اطلاعات یک کاربر.
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'data' => [
                'user' => $user,
            ],
        ]);
    }

    /**
     * ویرایش اطلاعات و سطح دسترسی کاربر.
     */
    public function update(
        UpdateUserRequest $request,
        User $user
    ): JsonResponse {
        $authenticatedUser = $request->user();

        if ($authenticatedUser->is($user)) {
            return response()->json([
                'message' => 'Manage your own account through the profile section.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $request->validated();

        if ($this->wouldRemoveLastActiveAdmin($user, $validated)) {
            return response()->json([
                'message' => 'At least one active administrator must remain.',
            ], Response::HTTP_CONFLICT);
        }

        DB::transaction(function () use ($user, $validated): void {
            $emailHasChanged = $user->email !== $validated['email'];

            $user->forceFill([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => $validated['role'],
                'is_active' => $validated['is_active'],
                'email_verified_at' => $emailHasChanged
                    ? null
                    : $user->email_verified_at,
            ])->save();

            if (! $user->isActive()) {
                $user->tokens()->delete();
            }
        });

        return response()->json([
            'message' => 'User updated successfully.',
            'data' => [
                'user' => $user->refresh(),
            ],
        ]);
    }

    /**
     * حذف کاربر.
     */
    public function destroy(
        Request $request,
        User $user
    ): JsonResponse {
        $authenticatedUser = $request->user();

        if ($authenticatedUser->is($user)) {
            return response()->json([
                'message' => 'You cannot delete your own account.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($this->isLastActiveAdmin($user)) {
            return response()->json([
                'message' => 'At least one active administrator must remain.',
            ], Response::HTTP_CONFLICT);
        }

        $avatarPath = $user->avatar_path;

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->delete();
        });

        if ($avatarPath !== null) {
            Storage::disk('public')->delete($avatarPath);
        }

        return response()->json([
            'message' => 'User deleted successfully.',
        ]);
    }

    /**
     * بررسی می‌کند ویرایش، آخرین ادمین فعال را حذف می‌کند یا خیر.
     *
     * @param  array<string, mixed>  $validated
     */
    private function wouldRemoveLastActiveAdmin(
        User $user,
        array $validated
    ): bool {
        if (
            $user->role !== UserRole::Admin
            || ! $user->isActive()
        ) {
            return false;
        }

        $willRemainActiveAdmin = $validated['role'] === UserRole::Admin->value
            && $validated['is_active'];

        return ! $willRemainActiveAdmin
            && User::query()
                ->where('role', UserRole::Admin->value)
                ->where('is_active', true)
                ->count() <= 1;
    }

    /**
     * بررسی می‌کند کاربر، آخرین ادمین فعال سیستم است یا خیر.
     */
    private function isLastActiveAdmin(User $user): bool
    {
        if (
            $user->role !== UserRole::Admin
            || ! $user->isActive()
        ) {
            return false;
        }

        return User::query()
            ->where('role', UserRole::Admin->value)
            ->where('is_active', true)
            ->count() <= 1;
    }
}
