<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProfileController extends Controller
{
    /**
     * ویرایش اطلاعات پروفایل کاربر.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $oldAvatarPath = $user->avatar_path;
        $newAvatarPath = null;

        $avatar = $request->file('avatar');

        if ($avatar !== null) {
            $newAvatarPath = $avatar->storePublicly(
                'avatars',
                'public'
            );
        }

        $attributes = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if ($user->email !== $validated['email']) {
            $attributes['email_verified_at'] = null;
        }

        if ($newAvatarPath !== null) {
            $attributes['avatar_path'] = $newAvatarPath;
        }

        try {
            $user->forceFill($attributes)->save();
        } catch (Throwable $exception) {
            if ($newAvatarPath !== null) {
                Storage::disk('public')->delete($newAvatarPath);
            }

            throw $exception;
        }

        if (
            $newAvatarPath !== null
            && $oldAvatarPath !== null
            && $oldAvatarPath !== $newAvatarPath
        ) {
            Storage::disk('public')->delete($oldAvatarPath);
        }

        return response()->json([
            'message' => 'Profile updated successfully.',
            'data' => [
                'user' => $user->refresh(),
            ],
        ]);
    }

    /**
     * تغییر رمز عبور و خروج از همه دستگاه‌ها.
     */
    public function updatePassword(
        UpdatePasswordRequest $request
    ): JsonResponse {
        $validated = $request->validated();
        $user = $request->user();

        $user->forceFill([
            'password' => $validated['password'],
        ])->save();

        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password updated successfully. Please log in again.',
        ]);
    }
}
