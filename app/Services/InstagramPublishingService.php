<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\ContinueInstagramPublication;
use App\Jobs\ProcessInstagramScheduledPublication;
use App\Models\InstagramAccount;
use App\Models\InstagramPublication;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Throwable;
use Illuminate\Support\Facades\DB;

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
            $image,
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
                    'Instagram access token is unavailable.',
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
                    'Instagram did not return a media container ID.',
                );
            }

            $publication->container_id = $containerId;
            $publication->status = 'processing';
            $publication->save();

            $publication = $this->continuePublication(
                $publication,
                $account,
            );

            $this->dispatchContinuationIfNeeded(
                $publication,
            );

            return $publication;
        } catch (Throwable $exception) {
            $publication->status = 'failed';
            $publication->error_message =
                $exception->getMessage();

            $publication->save();

            throw $exception;
        }
    }

    public function publishCarousel(
        Workspace $workspace,
        InstagramAccount $account,
        array $images,
        ?string $caption = null,
    ): InstagramPublication {
        $storedImages = $this->storeCarouselImages(
            $images,
        );

        try {
            $publication =
                $this->createCarouselPublicationRecord(
                    $workspace,
                    $account,
                    $storedImages,
                    'pending',
                    null,
                    $caption,
                );
        } catch (Throwable $exception) {
            $this->deleteStoredCarouselImages(
                $storedImages,
            );

            throw $exception;
        }

        try {
            $publication->status = 'processing';
            $publication->error_message = null;
            $publication->save();

            $publication = $this->continuePublication(
                $publication,
                $account,
            );

            $this->dispatchContinuationIfNeeded(
                $publication,
            );

            return $publication->load('mediaItems');
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
            $video,
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
                throw new RuntimeException(
                    'Instagram access token is unavailable.',
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
                throw new RuntimeException(
                    'Instagram did not return a reel container ID.',
                );
            }

            $publication->container_id = $containerId;
            $publication->status = 'processing';
            $publication->save();

            $publication = $this->continuePublication(
                $publication,
                $account,
            );

            $this->dispatchContinuationIfNeeded(
                $publication,
            );

            return $publication;
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
            $video,
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
                throw new RuntimeException(
                    'Instagram access token is unavailable.',
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
                throw new RuntimeException(
                    'Instagram did not return a story container ID.',
                );
            }

            $publication->container_id = $containerId;
            $publication->status = 'processing';
            $publication->save();

            $publication = $this->continuePublication(
                $publication,
                $account,
            );

            $this->dispatchContinuationIfNeeded(
                $publication,
            );

            return $publication;
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
            $image,
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
                throw new RuntimeException(
                    'Instagram access token is unavailable.',
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
                throw new RuntimeException(
                    'Instagram did not return a story image container ID.',
                );
            }

            $publication->container_id = $containerId;
            $publication->status = 'processing';
            $publication->save();

            $publication = $this->continuePublication(
                $publication,
                $account,
            );

            $this->dispatchContinuationIfNeeded(
                $publication,
            );

            return $publication;
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
        DateTimeInterface $scheduledAt,
        ?string $caption = null,
        array $options = [],
    ): InstagramPublication {
        $storedFile = match ($mediaKind) {
            'image' => $this->storageService->storeImage(
                $file,
            ),

            'video' => $this->storageService->storeVideo(
                $file,
            ),

            default => throw new RuntimeException(
                'Unsupported Instagram scheduled media kind.',
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
        )->delay(
            $scheduledAt,
        );

        return $publication;
    }

    public function scheduleCarouselPublication(
        Workspace $workspace,
        InstagramAccount $account,
        array $images,
        DateTimeInterface $scheduledAt,
        ?string $caption = null,
        array $options = [],
    ): InstagramPublication {
        $storedImages = $this->storeCarouselImages(
            $images,
        );

        try {
            $publication =
                $this->createCarouselPublicationRecord(
                    $workspace,
                    $account,
                    $storedImages,
                    'scheduled',
                    $scheduledAt,
                    $caption,
                    $options,
                );
        } catch (Throwable $exception) {
            $this->deleteStoredCarouselImages(
                $storedImages,
            );

            throw $exception;
        }

        ProcessInstagramScheduledPublication::dispatch(
            $publication->id,
        )->delay(
            $scheduledAt,
        );

        return $publication->load('mediaItems');
    }

    public function cancelScheduledPublication(
        InstagramPublication $publication,
    ): InstagramPublication {
        $publication->refresh();

        if ($publication->status === 'cancelled') {
            return $publication;
        }

        if ($publication->status !== 'scheduled') {
            throw new RuntimeException(
                'Only scheduled Instagram publications can be cancelled.',
            );
        }

        if ($publication->type === 'carousel') {
            $this->cleanupCarouselStagingFiles(
                $publication,
            );
        } else {
            $stagingPath = $publication->staging_path;

            if (
                is_string($stagingPath)
                && trim($stagingPath) !== ''
            ) {
                $this->storageService->delete(
                    $stagingPath,
                );
            }

            $publication->staging_path = null;
        }

        $publication->status = 'cancelled';
        $publication->error_message = null;
        $publication->save();

        return $publication->loadMissing(
            'mediaItems',
        );
    }

    public function reschedulePublication(
        InstagramPublication $publication,
        DateTimeInterface $scheduledAt,
    ): InstagramPublication {
        $publication->refresh();

        if ($publication->status !== 'scheduled') {
            throw new RuntimeException(
                'Only scheduled Instagram publications can be rescheduled.',
            );
        }

        $oldScheduledAt =
            $publication->scheduled_at;

        $newScheduledAt =
            CarbonImmutable::instance(
                $scheduledAt,
            )->utc();

        $publication->scheduled_at =
            $newScheduledAt;

        $publication->processing_started_at =
            null;

        $publication->error_message =
            null;

        $publication->save();

        /*
         * When moved earlier, the existing delayed job
         * would wake up too late, so dispatch a new job.
         *
         * When moved later, the existing job wakes up
         * at its old time, sees the future scheduled_at,
         * and releases itself until the new time.
         */
        if (
            $oldScheduledAt === null
            || $newScheduledAt->lt(
                $oldScheduledAt,
            )
        ) {
            ProcessInstagramScheduledPublication::dispatch(
                $publication->id,
            )->delay(
                $newScheduledAt,
            );
        }

        return $publication->refresh();
    }

    public function processScheduledPublication(
        InstagramPublication $publication,
    ): InstagramPublication {
        $publication->refresh();

        if (
            in_array(
                $publication->status,
                [
                    'published',
                    'failed',
                    'cancelled',
                ],
                true,
            )
        ) {
            return $publication;
        }

        $account =
            $publication->instagramAccount;

        if (
            $account === null
            || !$account->is_active
        ) {
            throw new RuntimeException(
                'Instagram account for scheduled publication is unavailable.',
            );
        }

        $accessToken =
            $account->getAccessToken();

        if (
            !is_string($accessToken)
            || trim($accessToken) === ''
        ) {
            throw new RuntimeException(
                'Instagram access token is unavailable.',
            );
        }

        if ($publication->type === 'carousel') {
            $publication->status = 'processing';

            $publication->processing_started_at ??=
                now();

            $publication->error_message = null;

            $publication->save();

            return $this->continueCarouselPublication(
                $publication,
                $account,
            );
        }

        /*
         * If an earlier Queue attempt already created
         * the Meta container, never create another one.
         */
        if (
            is_string($publication->container_id)
            && trim(
                $publication->container_id,
            ) !== ''
        ) {
            return $this->continuePublication(
                $publication,
                $account,
            );
        }

        $stagingPath =
            $publication->staging_path;

        if (
            !is_string($stagingPath)
            || trim($stagingPath) === ''
        ) {
            throw new RuntimeException(
                'Scheduled Instagram publication has no staging file.',
            );
        }

        $mediaUrl =
            $this->storageService->url(
                $stagingPath,
            );

        $instagramId =
            (string) $account->instagram_id;

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
                        $publication
                            ->options['share_to_feed']
                        ?? true
                    ),
                ),

            'story' => match (
            $publication->media_kind
            ) {
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

                default => throw new RuntimeException(
                    'Unsupported scheduled Instagram story media kind.',
                ),
            },

            default => throw new RuntimeException(
                'Unsupported scheduled Instagram publication type.',
            ),
        };

        $containerId =
            $container['id'] ?? null;

        if (
            !is_string($containerId)
            || trim($containerId) === ''
        ) {
            throw new RuntimeException(
                'Instagram did not return a container ID.',
            );
        }

        $publication->container_id =
            $containerId;

        $publication->status =
            'processing';

        $publication->processing_started_at ??=
            now();

        $publication->error_message =
            null;

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
        if (
            in_array(
                $publication->status,
                [
                    'published',
                    'failed',
                    'cancelled',
                ],
                true,
            )
        ) {
            return $publication;
        }

        if ($publication->type === 'carousel') {
            return $this->continueCarouselPublication(
                $publication,
                $account,
            );
        }

        $accessToken =
            $account->getAccessToken();

        if (
            !is_string($accessToken)
            || trim($accessToken) === ''
        ) {
            throw new RuntimeException(
                'Instagram access token is unavailable.',
            );
        }

        if (
            !is_string($publication->container_id)
            || trim(
                $publication->container_id,
            ) === ''
        ) {
            throw new RuntimeException(
                'Instagram publication has no container ID.',
            );
        }

        $containerStatus =
            $this->instagramApiService
                ->getContainerStatus(
                    $accessToken,
                    $publication->container_id,
                );

        $statusCode =
            $containerStatus['status_code']
            ?? null;

        if ($statusCode === 'FINISHED') {
            $published =
                $this->instagramApiService
                    ->publishContainer(
                        $accessToken,
                        (string) $account->instagram_id,
                        $publication->container_id,
                    );

            $publication->media_id =
                isset($published['id'])
                    ? (string) $published['id']
                    : null;

            $publication->status =
                'published';

            $publication->error_message =
                null;

            $publication->published_at =
                now();

            $publication->save();

            if (
                is_string(
                    $publication->staging_path,
                )
                && $publication->staging_path !== ''
            ) {
                $this->storageService->delete(
                    $publication->staging_path,
                );

                $publication->staging_path =
                    null;

                $publication->save();
            }

            return $publication;
        }

        if (
            $statusCode === 'ERROR'
            || $statusCode === 'EXPIRED'
        ) {
            $publication->status =
                'failed';

            $publication->error_message =
                is_string(
                    $containerStatus['status']
                    ?? null,
                )
                    ? $containerStatus['status']
                    : "Instagram container status: {$statusCode}";

            $publication->save();

            return $publication;
        }

        $publication->status =
            'processing';

        $publication->save();

        return $publication;
    }

    private function continueCarouselPublication(
        InstagramPublication $publication,
        InstagramAccount $account,
    ): InstagramPublication {
        $accessToken = $account->getAccessToken();

        if (
            !is_string($accessToken)
            || trim($accessToken) === ''
        ) {
            throw new RuntimeException(
                'Instagram access token is unavailable.',
            );
        }

        $publication->load('mediaItems');

        $mediaItems = $publication->mediaItems;

        if (
            $mediaItems->count() < 2
            || $mediaItems->count() > 10
        ) {
            throw new RuntimeException(
                'Instagram carousel requires between 2 and 10 images.',
            );
        }

        $instagramId =
            (string) $account->instagram_id;

        /*
         * Parent container does not exist yet.
         * Create/check every child first.
         */
        if (
            !is_string($publication->container_id)
            || trim($publication->container_id) === ''
        ) {
            $allChildrenFinished = true;

            foreach ($mediaItems as $mediaItem) {
                if ($mediaItem->media_kind !== 'image') {
                    throw new RuntimeException(
                        'Unsupported Instagram carousel media kind.',
                    );
                }

                if (
                    !is_string($mediaItem->container_id)
                    || trim($mediaItem->container_id) === ''
                ) {
                    $stagingPath =
                        $mediaItem->staging_path;

                    if (
                        !is_string($stagingPath)
                        || trim($stagingPath) === ''
                    ) {
                        throw new RuntimeException(
                            'Instagram carousel item has no staging file.',
                        );
                    }

                    $mediaUrl =
                        $this->storageService->url(
                            $stagingPath,
                        );

                    $container =
                        $this->instagramApiService
                            ->createCarouselImageItemContainer(
                                $accessToken,
                                $instagramId,
                                $mediaUrl,
                            );

                    $containerId =
                        $container['id'] ?? null;

                    if (
                        !is_string($containerId)
                        || trim($containerId) === ''
                    ) {
                        throw new RuntimeException(
                            'Instagram did not return a carousel child container ID.',
                        );
                    }

                    $mediaItem->container_id =
                        $containerId;

                    $mediaItem->container_status =
                        null;

                    $mediaItem->error_message =
                        null;

                    $mediaItem->save();
                }

                /*
                 * FINISHED is final for this child.
                 * There is no need to query it again.
                 */
                if (
                    $mediaItem->container_status ===
                    'FINISHED'
                ) {
                    continue;
                }

                $containerStatus =
                    $this->instagramApiService
                        ->getContainerStatus(
                            $accessToken,
                            $mediaItem->container_id,
                        );

                $statusCode =
                    $containerStatus['status_code']
                    ?? null;

                $mediaItem->container_status =
                    is_string($statusCode)
                        ? $statusCode
                        : null;

                if (
                    $statusCode === 'ERROR'
                    || $statusCode === 'EXPIRED'
                ) {
                    $errorMessage =
                        is_string(
                            $containerStatus['status']
                            ?? null,
                        )
                            ? $containerStatus['status']
                            : "Instagram carousel child status: {$statusCode}";

                    $mediaItem->error_message =
                        $errorMessage;

                    $mediaItem->save();

                    $publication->status = 'failed';
                    $publication->error_message =
                        $errorMessage;

                    $publication->save();

                    return $publication;
                }

                $mediaItem->error_message = null;
                $mediaItem->save();

                if ($statusCode !== 'FINISHED') {
                    $allChildrenFinished = false;
                }
            }

            if (!$allChildrenFinished) {
                $publication->status = 'processing';
                $publication->save();

                return $publication;
            }

            /*
             * All children are FINISHED.
             * Their order comes from mediaItems.position.
             */
            $childContainerIds =
                $mediaItems
                    ->pluck('container_id')
                    ->filter(
                        static fn (mixed $containerId): bool =>
                            is_string($containerId)
                            && trim($containerId) !== '',
                    )
                    ->values()
                    ->all();

            if (
                count($childContainerIds)
                !== $mediaItems->count()
            ) {
                throw new RuntimeException(
                    'Instagram carousel child containers are incomplete.',
                );
            }

            $parentContainer =
                $this->instagramApiService
                    ->createCarouselContainer(
                        $accessToken,
                        $instagramId,
                        $childContainerIds,
                        $publication->caption,
                    );

            $parentContainerId =
                $parentContainer['id'] ?? null;

            if (
                !is_string($parentContainerId)
                || trim($parentContainerId) === ''
            ) {
                throw new RuntimeException(
                    'Instagram did not return a carousel container ID.',
                );
            }

            $publication->container_id =
                $parentContainerId;

            $publication->status =
                'processing';

            $publication->save();
        }

        /*
         * From this point on container_id is the
         * parent CAROUSEL container.
         */
        $containerStatus =
            $this->instagramApiService
                ->getContainerStatus(
                    $accessToken,
                    $publication->container_id,
                );

        $statusCode =
            $containerStatus['status_code']
            ?? null;

        if ($statusCode === 'FINISHED') {
            $published =
                $this->instagramApiService
                    ->publishContainer(
                        $accessToken,
                        $instagramId,
                        $publication->container_id,
                    );

            $publication->media_id =
                isset($published['id'])
                    ? (string) $published['id']
                    : null;

            $publication->status =
                'published';

            $publication->error_message =
                null;

            $publication->published_at =
                now();

            $publication->save();

            $this->cleanupCarouselStagingFiles(
                $publication,
            );

            return $publication->load('mediaItems');
        }

        if (
            $statusCode === 'ERROR'
            || $statusCode === 'EXPIRED'
        ) {
            $publication->status =
                'failed';

            $publication->error_message =
                is_string(
                    $containerStatus['status']
                    ?? null,
                )
                    ? $containerStatus['status']
                    : "Instagram carousel container status: {$statusCode}";

            $publication->save();

            return $publication;
        }

        $publication->status = 'processing';
        $publication->save();

        return $publication;
    }

    private function storeCarouselImages(
        array $images,
    ): array {
        $images = array_values($images);

        if (
            count($images) < 2
            || count($images) > 10
        ) {
            throw new RuntimeException(
                'Instagram carousel requires between 2 and 10 images.',
            );
        }

        $storedImages = [];

        try {
            foreach ($images as $image) {
                if (!$image instanceof UploadedFile) {
                    throw new RuntimeException(
                        'Invalid Instagram carousel image.',
                    );
                }

                $storedImages[] =
                    $this->storageService->storeImage(
                        $image,
                    );
            }
        } catch (Throwable $exception) {
            $this->deleteStoredCarouselImages(
                $storedImages,
            );

            throw $exception;
        }

        return $storedImages;
    }

    private function createCarouselPublicationRecord(
        Workspace $workspace,
        InstagramAccount $account,
        array $storedImages,
        string $status,
        ?DateTimeInterface $scheduledAt,
        ?string $caption = null,
        array $options = [],
    ): InstagramPublication {
        return DB::transaction(
            function () use (
                $workspace,
                $account,
                $storedImages,
                $status,
                $scheduledAt,
                $caption,
                $options,
            ): InstagramPublication {
                $publication =
                    InstagramPublication::query()->create([
                        'workspace_id' =>
                            $workspace->id,

                        'instagram_account_id' =>
                            $account->id,

                        'type' =>
                            'carousel',

                        'media_kind' =>
                            'image',

                        'caption' =>
                            $caption,

                        'options' =>
                            $options,

                        'staging_path' =>
                            null,

                        'status' =>
                            $status,

                        'scheduled_at' =>
                            $scheduledAt,

                        'processing_started_at' =>
                            null,
                    ]);

                foreach (
                    $storedImages
                    as $position => $storedImage
                ) {
                    $publication
                        ->mediaItems()
                        ->create([
                            'media_kind' =>
                                'image',

                            'staging_path' =>
                                $storedImage['path'],

                            'position' =>
                                $position,

                            'container_id' =>
                                null,

                            'container_status' =>
                                null,

                            'error_message' =>
                                null,
                        ]);
                }

                return $publication->load(
                    'mediaItems',
                );
            }
        );
    }

    private function deleteStoredCarouselImages(
        array $storedImages,
    ): void {
        foreach ($storedImages as $storedImage) {
            $path = $storedImage['path'] ?? null;

            if (
                !is_string($path)
                || trim($path) === ''
            ) {
                continue;
            }

            $this->storageService->delete(
                $path,
            );
        }
    }

    private function cleanupCarouselStagingFiles(
        InstagramPublication $publication,
    ): void {
        $publication->load('mediaItems');

        foreach ($publication->mediaItems as $mediaItem) {
            $stagingPath =
                $mediaItem->staging_path;

            if (
                !is_string($stagingPath)
                || trim($stagingPath) === ''
            ) {
                continue;
            }

            $this->storageService->delete(
                $stagingPath,
            );

            $mediaItem->staging_path =
                null;

            $mediaItem->save();
        }
    }

    private function dispatchContinuationIfNeeded(
        InstagramPublication $publication,
    ): void {
        if (
            $publication->status !==
            'processing'
        ) {
            return;
        }

        ContinueInstagramPublication::dispatch(
            $publication->id,
        )->delay(
            now()->addSeconds(30),
        );
    }
}
