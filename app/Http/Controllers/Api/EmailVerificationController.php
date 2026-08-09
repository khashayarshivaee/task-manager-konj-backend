<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmailVerificationController extends Controller
{
    /**
     * Verify the email address from a signed email link.
     */
    public function verify(
        Request $request,
        int $id,
        string $hash,
    ): JsonResponse {
        $user = User::query()->findOrFail($id);

        if (
            ! hash_equals(
                $hash,
                sha1(
                    $user->getEmailForVerification()
                ),
            )
        ) {
            return response()->json([
                'message' => 'Invalid verification link.',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email address is already verified.',
                'data' => [
                    'verified' => true,
                ],
            ]);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'message' => 'Email address verified successfully.',
            'data' => [
                'verified' => true,
            ],
        ]);
    }

    /**
     * Resend the email verification link.
     */
    public function resend(
        Request $request,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email address is already verified.',
                'data' => [
                    'verified' => true,
                ],
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Verification email sent successfully.',
            'data' => [
                'verified' => false,
            ],
        ]);
    }
}
