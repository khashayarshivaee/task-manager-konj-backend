<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InstagramAccount;
use App\Models\InstagramPublication;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Throwable;
use App\Jobs\ProcessInstagramScheduledPublication;
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

    public function publishStoryImage(
        Workspace $workspace,
        InstagramAccount $account,
        UploadedFile $image,
    ): InstagramPublication {
        $storedImage = $this->storageService->storeImage(
            $image
        );

        $publication = InstagramPublication::query()->create([
            'workspace_id' => $workspace->id,
            'instagram_account_id' => $account->id,
            'type' => 'story',
            'caption' => null,
            'staging_path' => $storedImage['path'],
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
                ->createStoryImageContainer(
                    $accessToken,
                    (string) $account->instagram_id,
                    $storedImage['url'],
                );

            $containerId = $container['id'] ?? null;

            if (
                !is_string($containerId)
                || trim($containerId) === ''
            ) {
                throw new \RuntimeException(
                    'Instagram did not return a story image container ID.'
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

    public function schedulePublication(
        Workspace $workspace,
        InstagramAccount $account,
        UploadedFile $file,
        string $type,
        string $mediaKind,
        \DateTimeInterface $scheduledAt,
        ?string $caption = null,
        array $options = [],
    ): InstagramPublication {
        $storedFile = match ($mediaKind) {
            'image' => $this->storageService->storeImage($file),
            'video' => $this->storageService->storeVideo($file),

            default => throw new \RuntimeException(
                'Unsupported Instagram scheduled media kind.'
            ),
        };

        $publication = InstagramPublication::query()->create([
            'workspace_id' => $workspace->id,
            'instagram_account_id' => $account->id,
            'type' => $type,
            'media_kind' => $mediaKind,
            'caption' => $caption,
            'options' => $options,
            'staging_path' => $storedFile['path'],
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt,
            'processing_started_at' => null,
        ]);

        ProcessInstagramScheduledPublication::dispatch(
            $publication->id,
        )->delay($scheduledAt);

        return $publication;
    }

    public function processScheduledPublication(
        InstagramPublication $publication,
    ): InstagramPublication {
        $publication->refresh();

        if (in_array(
            $publication->status,
            [
                'published',
                'failed',
            ],
            true,
        )) {
            return $publication;
        }

        $account = $publication->instagramAccount;

        if ($account === null || !$account->is_active) {
            throw new \RuntimeException(
                'Instagram account for scheduled publication is unavailable.'
            );
        }

        $accessToken = $account->getAccessToken();

        if (
            !is_string($accessToken)
            || trim($accessToken) === ''
        ) {
            throw new \RuntimeException(
                'Instagram access token is unavailable.'
            );
        }

        /*
         * Important:
         * If a previous Queue attempt already created the Meta container,
         * never create another container.
         */
        if (
            is_string($publication->container_id)
            && trim($publication->container_id) !== ''
        ) {
            return $this->continuePublication(
                $publication,
                $account,
            );
        }

        $stagingPath = $publication->staging_path;

        if (
            !is_string($stagingPath)
            || trim($stagingPath) === ''
        ) {
            throw new \RuntimeException(
                'Scheduled Instagram publication has no staging file.'
            );
        }

        $mediaUrl = $this->storageService->url(
            $stagingPath
        );

        $instagramId = (string) $account->instagram_id;

        $container = match ($publication->type) {
            'image' => $this->instagramApiService
                ->createImageContainer(
                    $accessToken,
                    $instagramId,
                    $mediaUrl,
                    $publication->caption,
                ),

            'reel' => $this->instagramApiService
                ->createReelContainer(
                    $accessToken,
                    $instagramId,
                    $mediaUrl,
                    $publication->caption,
                    (bool) (
                        $publication->options['share_to_feed']
                        ?? true
                    ),
                ),

            'story' => match ($publication->media_kind) {
                'image' => $this->instagramApiService
                    ->createStoryImageContainer(
                        $accessToken,
                        $instagramId,
                        $mediaUrl,
                    ),

                'video' => $this->instagramApiService
                    ->createStoryVideoContainer(
                        $accessToken,
                        $instagramId,
                        $mediaUrl,
                    ),

                default => throw new \RuntimeException(
                    'Unsupported scheduled Instagram story media kind.'
                ),
            },

            default => throw new \RuntimeException(
                'Unsupported scheduled Instagram publication type.'
            ),
        };

        $containerId = $container['id'] ?? null;

        if (
            !is_string($containerId)
            || trim($containerId) === ''
        ) {
            throw new \RuntimeException(
                'Instagram did not return a container ID.'
            );
        }

        $publication->container_id = $containerId;
        $publication->status = 'processing';
        $publication->processing_started_at ??= now();
        $publication->error_message = null;
        $publication->save();

        return $this->continuePublication(
            $publication,
            $account,
        );
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
