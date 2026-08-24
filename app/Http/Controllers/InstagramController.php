<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\InstagramConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class InstagramController extends Controller
{
    public function __construct(
        private InstagramConnectionService $instagramConnectionService
    ) {
    }

    public function connect(
        Workspace $workspace
    ): JsonResponse {
        Gate::authorize(
            'update',
            $workspace
        );

        $accessToken = config(
            'services.instagram.access_token'
        );

        if (
            !is_string($accessToken)
            || trim($accessToken) === ''
        ) {
            return response()->json(
                [
                    'message' =>
                        'Instagram access token is not configured.',
                ],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        $account =
            $this->instagramConnectionService->connect(
                $accessToken,
                $workspace->id
            );

        return response()->json([
            'message' =>
                'Instagram account connected successfully.',

            'data' => [
                'account' => [
                    'id' =>
                        $account->id,

                    'workspace_id' =>
                        $account->workspace_id,

                    'instagram_id' =>
                        $account->instagram_id,

                    'username' =>
                        $account->username,

                    'is_active' =>
                        $account->is_active,
                ],
            ],
        ]);
    }


    public function show(
        Workspace $workspace
    ): JsonResponse {
        Gate::authorize(
            'update',
            $workspace
        );

        $account = $workspace
            ->instagramAccounts()
            ->where('is_active', true)
            ->first();

        if ($account === null) {
            return response()->json([
                'data' => [
                    'connected' => false,
                    'account' => null,
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'connected' => true,
                'account' => [
                    'id' => $account->id,
                    'workspace_id' => $account->workspace_id,
                    'instagram_id' => $account->instagram_id,
                    'username' => $account->username,
                    'is_active' => $account->is_active,
                ],
            ],
        ]);
    }


    public function disconnect(
        Workspace $workspace
    ): JsonResponse {
        Gate::authorize(
            'update',
            $workspace
        );

        $account =
            $this->instagramConnectionService->disconnect(
                $workspace->id
            );

        if ($account === null) {
            return response()->json([
                'message' => 'No active Instagram account is connected.',
                'data' => [
                    'connected' => false,
                ],
            ]);
        }

        return response()->json([
            'message' => 'Instagram account disconnected successfully.',
            'data' => [
                'connected' => false,
                'account' => [
                    'id' => $account->id,
                    'workspace_id' => $account->workspace_id,
                    'instagram_id' => $account->instagram_id,
                    'username' => $account->username,
                    'is_active' => $account->is_active,
                ],
            ],
        ]);
    }

    public function profile(
        Workspace $workspace
    ): JsonResponse {
        Gate::authorize(
            'update',
            $workspace
        );

        $account = $workspace
            ->instagramAccounts()
            ->where('is_active', true)
            ->first();

        if ($account === null) {
            return response()->json(
                [
                    'message' =>
                        'No active Instagram account is connected.',
                ],
                Response::HTTP_NOT_FOUND,
            );
        }

        $accessToken = $account->getAccessToken();

        if (
            !is_string($accessToken)
            || trim($accessToken) === ''
        ) {
            return response()->json(
                [
                    'message' =>
                        'Instagram access token is unavailable.',
                ],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        $profile = app(
            \App\Services\InstagramApiService::class
        )->getProfile(
            $accessToken
        );

        return response()->json([
            'data' => [
                'profile' => $profile,
            ],
        ]);
    }

    public function insights(
        Workspace $workspace
    ): JsonResponse {
        Gate::authorize(
            'update',
            $workspace
        );

        $account = $workspace
            ->instagramAccounts()
            ->where('is_active', true)
            ->first();

        if ($account === null) {
            return response()->json(
                [
                    'message' =>
                        'No active Instagram account is connected.',
                ],
                Response::HTTP_NOT_FOUND,
            );
        }

        $accessToken = $account->getAccessToken();

        if (
            !is_string($accessToken)
            || trim($accessToken) === ''
        ) {
            return response()->json(
                [
                    'message' =>
                        'Instagram access token is unavailable.',
                ],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        $insights = app(
            \App\Services\InstagramApiService::class
        )->getAccountInsights(
            $accessToken,
            (string) $account->instagram_id
        );

        return response()->json([
            'data' => [
                'insights' => $insights,
            ],
        ]);
    }

    public function dashboard(
        Workspace $workspace
    ): JsonResponse {
        Gate::authorize(
            'update',
            $workspace
        );

        $account = $workspace
            ->instagramAccounts()
            ->where('is_active', true)
            ->first();

        if ($account === null) {
            return response()->json([
                'data' => [
                    'connected' => false,
                    'account' => null,
                    'profile' => null,
                    'insights' => null,
                ],
            ]);
        }

        $accessToken = $account->getAccessToken();

        if (
            !is_string($accessToken)
            || trim($accessToken) === ''
        ) {
            return response()->json(
                [
                    'message' =>
                        'Instagram access token is unavailable.',
                ],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        $instagramApiService = app(
            \App\Services\InstagramApiService::class
        );

        $profile = $instagramApiService->getProfile(
            $accessToken
        );

        $insights =
            $instagramApiService->getAccountInsights(
                $accessToken,
                (string) $account->instagram_id
            );

        return response()->json([
            'data' => [
                'connected' => true,

                'account' => [
                    'id' => $account->id,
                    'workspace_id' =>
                        $account->workspace_id,
                    'instagram_id' =>
                        $account->instagram_id,
                    'username' =>
                        $account->username,
                    'is_active' =>
                        $account->is_active,
                ],

                'profile' => $profile,

                'insights' => $insights,
            ],
        ]);
    }

    public function media(
        Workspace $workspace
    ): JsonResponse {
        Gate::authorize(
            'update',
            $workspace
        );

        $account = $workspace
            ->instagramAccounts()
            ->where('is_active', true)
            ->first();

        if ($account === null) {
            return response()->json(
                [
                    'message' =>
                        'No active Instagram account is connected.',
                ],
                Response::HTTP_NOT_FOUND,
            );
        }

        $accessToken = $account->getAccessToken();

        if (
            !is_string($accessToken)
            || trim($accessToken) === ''
        ) {
            return response()->json(
                [
                    'message' =>
                        'Instagram access token is unavailable.',
                ],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        $media = app(
            \App\Services\InstagramApiService::class
        )->getRecentMedia(
            $accessToken,
            (string) $account->instagram_id,
            12
        );

        return response()->json([
            'data' => [
                'media' => $media['data'],
                'paging' => $media['paging'],
            ],
        ]);
    }
}
