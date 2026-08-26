<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\InstagramConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;
use App\Models\InstagramPublication;
use Carbon\CarbonImmutable;
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

    public function publishImage(
        Request $request,
        Workspace $workspace,
        \App\Services\InstagramPublishingService $publishingService
    ): JsonResponse {
        Gate::authorize(
            'update',
            $workspace
        );

        $validated = $request->validate([
            'image' => [
                'required',
                'file',
                'mimes:jpg,jpeg',
                'max:8192',
            ],
            'caption' => [
                'nullable',
                'string',
                'max:2200',
            ],
        ]);

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

        $image = $request->file('image');

        if ($image === null) {
            return response()->json(
                [
                    'message' =>
                        'Instagram image upload is required.',
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $publication = $publishingService->publishImage(
            $workspace,
            $account,
            $image,
            $validated['caption'] ?? null,
        );

        $httpStatus = $publication->status === 'published'
            ? Response::HTTP_CREATED
            : Response::HTTP_ACCEPTED;

        return response()->json(
            [
                'message' =>
                    $publication->status === 'published'
                        ? 'Instagram image published successfully.'
                        : 'Instagram image accepted for processing.',

                'data' => [
                    'publication' => [
                        'id' => $publication->id,
                        'workspace_id' =>
                            $publication->workspace_id,
                        'instagram_account_id' =>
                            $publication->instagram_account_id,
                        'type' =>
                            $publication->type,
                        'caption' =>
                            $publication->caption,
                        'container_id' =>
                            $publication->container_id,
                        'media_id' =>
                            $publication->media_id,
                        'status' =>
                            $publication->status,
                        'published_at' =>
                            $publication->published_at?->toISOString(),
                    ],
                ],
            ],
            $httpStatus,
        );
    }

    public function continuePublication(
        Workspace $workspace,
        InstagramPublication $publication,
        \App\Services\InstagramPublishingService $publishingService
    ): JsonResponse {
        Gate::authorize(
            'update',
            $workspace
        );

        if ($publication->workspace_id !== $workspace->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $account = $publication->instagramAccount;

        if (
            $account === null
            || $account->workspace_id !== $workspace->id
        ) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $publication = $publishingService
            ->continuePublication(
                $publication,
                $account
            );

        $httpStatus = match ($publication->status) {
            'processing' => Response::HTTP_ACCEPTED,
            'published' => Response::HTTP_OK,
            'failed' => Response::HTTP_UNPROCESSABLE_ENTITY,
            default => Response::HTTP_OK,
        };

        return response()->json(
            [
                'data' => [
                    'publication' => [
                        'id' =>
                            $publication->id,

                        'workspace_id' =>
                            $publication->workspace_id,

                        'instagram_account_id' =>
                            $publication->instagram_account_id,

                        'type' =>
                            $publication->type,

                        'caption' =>
                            $publication->caption,

                        'container_id' =>
                            $publication->container_id,

                        'media_id' =>
                            $publication->media_id,

                        'status' =>
                            $publication->status,

                        'error_message' =>
                            $publication->error_message,

                        'published_at' =>
                            $publication->published_at?->toISOString(),
                    ],
                ],
            ],
            $httpStatus,
        );
    }

    public function publishReel(
        Request $request,
        Workspace $workspace,
        \App\Services\InstagramPublishingService $publishingService
    ): JsonResponse {
        Gate::authorize(
            'update',
            $workspace
        );

        $validated = $request->validate([
            'video' => [
                'required',
                'file',
                'mimes:mp4,mov',
                'max:102400',
            ],
            'caption' => [
                'nullable',
                'string',
                'max:2200',
            ],
            'share_to_feed' => [
                'nullable',
                'boolean',
            ],
        ]);

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

        $video = $request->file('video');

        if ($video === null) {
            return response()->json(
                [
                    'message' =>
                        'Instagram reel video upload is required.',
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $publication = $publishingService->publishReel(
            $workspace,
            $account,
            $video,
            $validated['caption'] ?? null,
            $request->boolean('share_to_feed', true),
        );

        $httpStatus = $publication->status === 'published'
            ? Response::HTTP_CREATED
            : Response::HTTP_ACCEPTED;

        return response()->json(
            [
                'message' =>
                    $publication->status === 'published'
                        ? 'Instagram reel published successfully.'
                        : 'Instagram reel accepted for processing.',

                'data' => [
                    'publication' => [
                        'id' => $publication->id,
                        'workspace_id' =>
                            $publication->workspace_id,
                        'instagram_account_id' =>
                            $publication->instagram_account_id,
                        'type' =>
                            $publication->type,
                        'caption' =>
                            $publication->caption,
                        'container_id' =>
                            $publication->container_id,
                        'media_id' =>
                            $publication->media_id,
                        'status' =>
                            $publication->status,
                        'error_message' =>
                            $publication->error_message,
                        'published_at' =>
                            $publication->published_at?->toISOString(),
                    ],
                ],
            ],
            $httpStatus,
        );
    }

    public function publishStoryVideo(
        Request $request,
        Workspace $workspace,
        \App\Services\InstagramPublishingService $publishingService
    ): JsonResponse {
        Gate::authorize(
            'update',
            $workspace
        );

        $request->validate([
            'video' => [
                'required',
                'file',
                'mimes:mp4,mov',
                'max:102400',
            ],
        ]);

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

        $video = $request->file('video');

        if ($video === null) {
            return response()->json(
                [
                    'message' =>
                        'Instagram story video upload is required.',
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $publication = $publishingService->publishStoryVideo(
            $workspace,
            $account,
            $video,
        );

        $httpStatus = $publication->status === 'published'
            ? Response::HTTP_CREATED
            : Response::HTTP_ACCEPTED;

        return response()->json(
            [
                'message' =>
                    $publication->status === 'published'
                        ? 'Instagram story published successfully.'
                        : 'Instagram story accepted for processing.',

                'data' => [
                    'publication' => [
                        'id' => $publication->id,
                        'workspace_id' =>
                            $publication->workspace_id,
                        'instagram_account_id' =>
                            $publication->instagram_account_id,
                        'type' =>
                            $publication->type,
                        'container_id' =>
                            $publication->container_id,
                        'media_id' =>
                            $publication->media_id,
                        'status' =>
                            $publication->status,
                        'error_message' =>
                            $publication->error_message,
                        'published_at' =>
                            $publication->published_at?->toISOString(),
                    ],
                ],
            ],
            $httpStatus,
        );
    }

    public function publishStoryImage(
        Request $request,
        Workspace $workspace,
        \App\Services\InstagramPublishingService $publishingService
    ): JsonResponse {
        Gate::authorize(
            'update',
            $workspace
        );

        $request->validate([
            'image' => [
                'required',
                'file',
                'mimes:jpg,jpeg',
                'max:8192',
            ],
        ]);

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

        $image = $request->file('image');

        if ($image === null) {
            return response()->json(
                [
                    'message' =>
                        'Instagram story image upload is required.',
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $publication = $publishingService->publishStoryImage(
            $workspace,
            $account,
            $image,
        );

        $httpStatus = $publication->status === 'published'
            ? Response::HTTP_CREATED
            : Response::HTTP_ACCEPTED;

        return response()->json(
            [
                'message' =>
                    $publication->status === 'published'
                        ? 'Instagram story image published successfully.'
                        : 'Instagram story image accepted for processing.',

                'data' => [
                    'publication' => [
                        'id' => $publication->id,
                        'workspace_id' =>
                            $publication->workspace_id,
                        'instagram_account_id' =>
                            $publication->instagram_account_id,
                        'type' =>
                            $publication->type,
                        'container_id' =>
                            $publication->container_id,
                        'media_id' =>
                            $publication->media_id,
                        'status' =>
                            $publication->status,
                        'error_message' =>
                            $publication->error_message,
                        'published_at' =>
                            $publication->published_at?->toISOString(),
                    ],
                ],
            ],
            $httpStatus,
        );
    }

    public function comments(
        Workspace $workspace,
        string $mediaId,
        \App\Services\InstagramApiService $instagramApiService
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

        $comments = $instagramApiService->getMediaComments(
            $accessToken,
            $mediaId,
        );

        return response()->json([
            'data' => $comments,
        ]);
    }

    public function replyToComment(
        Request $request,
        Workspace $workspace,
        string $commentId,
        \App\Services\InstagramApiService $instagramApiService
    ): JsonResponse {
        Gate::authorize(
            'update',
            $workspace
        );

        $validated = $request->validate([
            'message' => [
                'required',
                'string',
                'max:1000',
            ],
        ]);

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

        $reply = $instagramApiService->replyToComment(
            $accessToken,
            $commentId,
            $validated['message'],
        );

        return response()->json([
            'message' => 'Instagram comment reply sent successfully.',
            'data' => $reply,
        ]);
    }

    public function hideComment(
        Workspace $workspace,
        string $commentId,
        \App\Services\InstagramApiService $instagramApiService
    ): JsonResponse {
        Gate::authorize('update', $workspace);

        $account = $workspace
            ->instagramAccounts()
            ->where('is_active', true)
            ->first();

        if ($account === null) {
            return response()->json(
                [
                    'message' => 'No active Instagram account is connected.',
                ],
                Response::HTTP_NOT_FOUND,
            );
        }

        $accessToken = $account->getAccessToken();

        if (!is_string($accessToken) || trim($accessToken) === '') {
            return response()->json(
                [
                    'message' => 'Instagram access token is unavailable.',
                ],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        $result = $instagramApiService->hideComment(
            $accessToken,
            $commentId,
        );

        return response()->json([
            'message' => 'Instagram comment hidden successfully.',
            'data' => $result,
        ]);
    }

    public function unhideComment(
        Workspace $workspace,
        string $commentId,
        \App\Services\InstagramApiService $instagramApiService
    ): JsonResponse {
        Gate::authorize('update', $workspace);

        $account = $workspace
            ->instagramAccounts()
            ->where('is_active', true)
            ->first();

        if ($account === null) {
            return response()->json(
                [
                    'message' => 'No active Instagram account is connected.',
                ],
                Response::HTTP_NOT_FOUND,
            );
        }

        $accessToken = $account->getAccessToken();

        if (!is_string($accessToken) || trim($accessToken) === '') {
            return response()->json(
                [
                    'message' => 'Instagram access token is unavailable.',
                ],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        $result = $instagramApiService->unhideComment(
            $accessToken,
            $commentId,
        );

        return response()->json([
            'message' => 'Instagram comment unhidden successfully.',
            'data' => $result,
        ]);
    }

    public function deleteComment(
        Workspace $workspace,
        string $commentId,
        \App\Services\InstagramApiService $instagramApiService
    ): JsonResponse {
        Gate::authorize('update', $workspace);

        $account = $workspace
            ->instagramAccounts()
            ->where('is_active', true)
            ->first();

        if ($account === null) {
            return response()->json(
                [
                    'message' => 'No active Instagram account is connected.',
                ],
                Response::HTTP_NOT_FOUND,
            );
        }

        $accessToken = $account->getAccessToken();

        if (!is_string($accessToken) || trim($accessToken) === '') {
            return response()->json(
                [
                    'message' => 'Instagram access token is unavailable.',
                ],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        $result = $instagramApiService->deleteComment(
            $accessToken,
            $commentId,
        );

        return response()->json([
            'message' => 'Instagram comment deleted successfully.',
            'data' => $result,
        ]);
    }

    public function conversations(
        Workspace $workspace,
        \App\Services\InstagramApiService $instagramApiService
    ): JsonResponse {
        Gate::authorize('update', $workspace);

        $account = $workspace
            ->instagramAccounts()
            ->where('is_active', true)
            ->first();

        if ($account === null) {
            return response()->json(
                [
                    'message' => 'No active Instagram account is connected.',
                ],
                Response::HTTP_NOT_FOUND,
            );
        }

        $accessToken = $account->getAccessToken();

        if (!is_string($accessToken) || trim($accessToken) === '') {
            return response()->json(
                [
                    'message' => 'Instagram access token is unavailable.',
                ],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        $conversations = $instagramApiService->getConversations(
            $accessToken,
        );

        return response()->json([
            'data' => $conversations,
        ]);
    }

    public function conversation(
        Workspace $workspace,
        string $conversationId,
        \App\Services\InstagramApiService $instagramApiService
    ): JsonResponse {
        Gate::authorize('update', $workspace);

        $account = $workspace
            ->instagramAccounts()
            ->where('is_active', true)
            ->first();

        if ($account === null) {
            return response()->json(
                [
                    'message' => 'No active Instagram account is connected.',
                ],
                Response::HTTP_NOT_FOUND,
            );
        }

        $accessToken = $account->getAccessToken();

        if (!is_string($accessToken) || trim($accessToken) === '') {
            return response()->json(
                [
                    'message' => 'Instagram access token is unavailable.',
                ],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        $conversation = $instagramApiService->getConversation(
            $accessToken,
            $conversationId,
        );

        return response()->json([
            'data' => $conversation,
        ]);
    }

    public function message(
        Workspace $workspace,
        string $messageId,
        \App\Services\InstagramApiService $instagramApiService
    ): JsonResponse {
        Gate::authorize('update', $workspace);

        $account = $workspace
            ->instagramAccounts()
            ->where('is_active', true)
            ->first();

        if ($account === null) {
            return response()->json(
                [
                    'message' => 'No active Instagram account is connected.',
                ],
                Response::HTTP_NOT_FOUND,
            );
        }

        $accessToken = $account->getAccessToken();

        if (!is_string($accessToken) || trim($accessToken) === '') {
            return response()->json(
                [
                    'message' => 'Instagram access token is unavailable.',
                ],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        $message = $instagramApiService->getMessage(
            $accessToken,
            $messageId,
        );

        return response()->json([
            'data' => $message,
        ]);
    }

    public function sendMessage(
        Request $request,
        Workspace $workspace,
        \App\Services\InstagramApiService $instagramApiService
    ): JsonResponse {
        Gate::authorize('update', $workspace);

        $validated = $request->validate([
            'recipient_id' => [
                'required',
                'string',
                'max:255',
            ],
            'message' => [
                'required',
                'string',
                'max:1000',
            ],
        ]);

        $account = $workspace
            ->instagramAccounts()
            ->where('is_active', true)
            ->first();

        if ($account === null) {
            return response()->json(
                [
                    'message' => 'No active Instagram account is connected.',
                ],
                Response::HTTP_NOT_FOUND,
            );
        }

        $accessToken = $account->getAccessToken();

        if (!is_string($accessToken) || trim($accessToken) === '') {
            return response()->json(
                [
                    'message' => 'Instagram access token is unavailable.',
                ],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        $result = $instagramApiService->sendMessage(
            $accessToken,
            $validated['recipient_id'],
            $validated['message'],
        );

        return response()->json([
            'message' => 'Instagram message sent successfully.',
            'data' => $result,
        ]);
    }

    public function schedulePublication(
        Request $request,
        Workspace $workspace,
        \App\Services\InstagramPublishingService $publishingService
    ): JsonResponse {
        Gate::authorize('update', $workspace);

        $validated = $request->validate([
            'type' => [
                'required',
                'string',
                'in:image,reel,story',
            ],
            'media_kind' => [
                'required',
                'string',
                'in:image,video',
            ],
            'file' => [
                'required',
                'file',
                'mimes:jpg,jpeg,mp4,mov',
                'max:102400',
            ],
            'caption' => [
                'nullable',
                'string',
                'max:2200',
            ],
            'scheduled_at' => [
                'required',
                'date',
                'after:now',
            ],
            'share_to_feed' => [
                'nullable',
                'boolean',
            ],
        ]);

        $type = $validated['type'];
        $mediaKind = $validated['media_kind'];

        $validCombination = match ($type) {
            'image' => $mediaKind === 'image',
            'reel' => $mediaKind === 'video',
            'story' => in_array(
                $mediaKind,
                ['image', 'video'],
                true,
            ),
            default => false,
        };

        if (!$validCombination) {
            return response()->json(
                [
                    'message' =>
                        'Invalid Instagram publication type and media kind combination.',
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $file = $request->file('file');

        if ($file === null) {
            return response()->json(
                [
                    'message' =>
                        'Instagram publication file is required.',
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $extension = strtolower(
            $file->getClientOriginalExtension()
        );

        if (
            $mediaKind === 'image'
            && !in_array($extension, ['jpg', 'jpeg'], true)
        ) {
            return response()->json(
                [
                    'message' =>
                        'Scheduled Instagram images must be JPG or JPEG.',
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if (
            $mediaKind === 'video'
            && !in_array($extension, ['mp4', 'mov'], true)
        ) {
            return response()->json(
                [
                    'message' =>
                        'Scheduled Instagram videos must be MP4 or MOV.',
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

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

        $options = [];

        if ($type === 'reel') {
            $options['share_to_feed'] = $request->boolean(
                'share_to_feed',
                true,
            );
        }

        $publication = $publishingService->schedulePublication(
            $workspace,
            $account,
            $file,
            $type,
            $mediaKind,
            CarbonImmutable::parse(
                $validated['scheduled_at']
            )->utc(),
            $validated['caption'] ?? null,
            $options,
        );

        return response()->json(
            [
                'message' =>
                    'Instagram publication scheduled successfully.',
                'data' => [
                    'publication' => [
                        'id' => $publication->id,
                        'type' => $publication->type,
                        'media_kind' => $publication->media_kind,
                        'caption' => $publication->caption,
                        'status' => $publication->status,
                        'scheduled_at' =>
                            $publication->scheduled_at?->toISOString(),
                        'options' => $publication->options,
                    ],
                ],
            ],
            Response::HTTP_CREATED,
        );
    }
}
