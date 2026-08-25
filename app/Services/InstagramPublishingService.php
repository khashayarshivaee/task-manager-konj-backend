<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InstagramAccount;
use App\Models\InstagramPublication;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Throwable;

class InstagramPublishingService
{
    public function __construct(
        private InstagramApiService $instagramApiService,
        private InstagramPublishingStorageService $storageService,
    ) {
    }

    public function publishImage(
        Workspace $workspace,
        InstagramAccount $account,
        UploadedFile $image,
        ?string $caption = null,
    ): InstagramPublication {
        $storedImage = $this->storageService->storeImage(
            $image
        );

        $publication = InstagramPublication::query()->create([
            'workspace_id' => $workspace->id,
            'instagram_account_id' => $account->id,
            'type' => 'image',
            'caption' => $caption,
            'staging_path' => $storedImage['path'],
            'status' => 'pending',
        ]);

        try {
            $accessToken = $account->getAccessToken();

            if (
                !is_string($accessToken)
                || trim($accessToken) === ''
            ) {
                throw new RuntimeException(
                    'Instagram access token is unavailable.'
                );
            }

            $container = $this->instagramApiService
                ->createImageContainer(
                    $accessToken,
                    (string) $account->instagram_id,
                    $storedImage['url'],
                    $caption,
                );

            $containerId = $container['id'] ?? null;

            if (
                !is_string($containerId)
                || trim($containerId) === ''
            ) {
                throw new RuntimeException(
                    'Instagram did not return a media container ID.'
                );
            }

            $publication->container_id = $containerId;
            $publication->status = 'processing';
            $publication->save();

            return $this->continuePublication(
                $publication,
                $account,
            );
        } catch (Throwable $exception) {
            $publication->status = 'failed';
            $publication->error_message =
                $exception->getMessage();

            $publication->save();

            throw $exception;
        }
    }

    public function publishReel(
        Workspace $workspace,
        InstagramAccount $account,
        UploadedFile $video,
        ?string $caption = null,
        bool $shareToFeed = true,
    ): InstagramPublication {
        $storedVideo = $this->storageService->storeVideo(
            $video
        );

        $publication = InstagramPublication::query()->create([
            'workspace_id' => $workspace->id,
            'instagram_account_id' => $account->id,
            'type' => 'reel',
            'caption' => $caption,
            'staging_path' => $storedVideo['path'],
            'status' => 'pending',
        ]);

        try {
            $accessToken = $account->getAccessToken();

            if (
                !is_string($accessToken)
                || trim($accessToken) === ''
            ) {
                throw new \RuntimeException(
                    'Instagram access token is unavailable.'
                );
            }

            $container = $this->instagramApiService
                ->createReelContainer(
                    $accessToken,
                    (string) $account->instagram_id,
                    $storedVideo['url'],
                    $caption,
                    $shareToFeed,
                );

            $containerId = $container['id'] ?? null;

            if (
                !is_string($containerId)
                || trim($containerId) === ''
            ) {
                throw new \RuntimeException(
                    'Instagram did not return a reel container ID.'
                );
            }

            $publication->container_id = $containerId;
            $publication->status = 'processing';
            $publication->save();

            return $this->continuePublication(
                $publication,
                $account,
            );
        } catch (Throwable $exception) {
            $publication->status = 'failed';
            $publication->error_message =
                $exception->getMessage();

            $publication->save();

            throw $exception;
        }
    }

    public function publishStoryVideo(
        Workspace $workspace,
        InstagramAccount $account,
        UploadedFile $video,
    ): InstagramPublication {
        $storedVideo = $this->storageService->storeVideo(
            $video
        );

        $publication = InstagramPublication::query()->create([
            'workspace_id' => $workspace->id,
            'instagram_account_id' => $account->id,
            'type' => 'story',
            'caption' => null,
            'staging_path' => $storedVideo['path'],
            'status' => 'pending',
        ]);

        try {
            $accessToken = $account->getAccessToken();

            if (
                !is_string($accessToken)
                || trim($accessToken) === ''
            ) {
                throw new \RuntimeException(
                    'Instagram access token is unavailable.'
                );
            }

            $container = $this->instagramApiService
                ->createStoryVideoContainer(
                    $accessToken,
                    (string) $account->instagram_id,
                    $storedVideo['url'],
                );

            $containerId = $container['id'] ?? null;

            if (
                !is_string($containerId)
                || trim($containerId) === ''
            ) {
                throw new \RuntimeException(
                    'Instagram did not return a story container ID.'
                );
            }

            $publication->container_id = $containerId;
            $publication->status = 'processing';
            $publication->save();

            return $this->continuePublication(
                $publication,
                $account,
            );
        } catch (Throwable $exception) {
            $publication->status = 'failed';
            $publication->error_message =
                $exception->getMessage();

            $publication->save();

            throw $exception;
        }
    }

    public function continuePublication(
        InstagramPublication $publication,
        InstagramAccount $account,
    ): InstagramPublication {
        if ($publication->status === 'published') {
            return $publication;
        }

        if ($publication->status === 'failed') {
            return $publication;
        }

        $accessToken = $account->getAccessToken();

        if (
            !is_string($accessToken)
            || trim($accessToken) === ''
        ) {
            throw new RuntimeException(
                'Instagram access token is unavailable.'
            );
        }

        if (
            !is_string($publication->container_id)
            || trim($publication->container_id) === ''
        ) {
            throw new RuntimeException(
                'Instagram publication has no container ID.'
            );
        }

        $containerStatus = $this->instagramApiService
            ->getContainerStatus(
                $accessToken,
                $publication->container_id,
            );

        $statusCode =
            $containerStatus['status_code'] ?? null;

        if ($statusCode === 'FINISHED') {
            $published = $this->instagramApiService
                ->publishContainer(
                    $accessToken,
                    (string) $account->instagram_id,
                    $publication->container_id,
                );

            $publication->media_id =
                isset($published['id'])
                    ? (string) $published['id']
                    : null;

            $publication->status = 'published';
            $publication->error_message = null;
            $publication->published_at = now();
            $publication->save();

            if (
                is_string($publication->staging_path)
                && $publication->staging_path !== ''
            ) {
                $this->storageService->delete(
                    $publication->staging_path
                );

                $publication->staging_path = null;
                $publication->save();
            }

            return $publication;
        }

        if (
            $statusCode === 'ERROR'
            || $statusCode === 'EXPIRED'
        ) {
            $publication->status = 'failed';

            $publication->error_message =
                is_string($containerStatus['status'] ?? null)
                    ? $containerStatus['status']
                    : "Instagram container status: {$statusCode}";

            $publication->save();

            return $publication;
        }

        $publication->status = 'processing';
        $publication->save();

        return $publication;
    }
}
