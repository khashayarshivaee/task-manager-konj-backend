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
    ): Response {
        $user = User::query()->findOrFail($id);

        if (
            ! hash_equals(
                $hash,
                sha1(
                    $user->getEmailForVerification()
                ),
            )
        ) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' =>
                        'Invalid verification link.',
                ], Response::HTTP_FORBIDDEN);
            }

            return response()->view(
                'auth.email-verification-result',
                [
                    'verified' => false,

                    'title' =>
                        'Verification link is invalid',

                    'message' =>
                        'This verification link is invalid or can no longer be used.',
                ],
                Response::HTTP_FORBIDDEN,
            );
        }

        if ($user->hasVerifiedEmail()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' =>
                        'Email address is already verified.',

                    'data' => [
                        'verified' => true,
                    ],
                ]);
            }

            return response()->view(
                'auth.email-verification-result',
                [
                    'verified' => true,

                    'title' =>
                        'Email already verified',

                    'message' =>
                        'Your email address has already been verified. Your account is ready to use.',
                ],
            );
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' =>
                    'Email address verified successfully.',

                'data' => [
                    'verified' => true,
                ],
            ]);
        }

        return response()->view(
            'auth.email-verification-result',
            [
                'verified' => true,

                'title' =>
                    'Email verified',

                'message' =>
                    'Your email address has been verified successfully. Your Konj account is ready.',
            ],
        );
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
                'message' =>
                    'Email address is already verified.',

                'data' => [
                    'verified' => true,
                ],
            ]);
        }

        $user
            ->sendEmailVerificationNotification();

        return response()->json([
            'message' =>
                'Verification email sent successfully.',

            'data' => [
                'verified' => false,
            ],
        ]);
    }
}
