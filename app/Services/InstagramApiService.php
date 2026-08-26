<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class InstagramApiService
{
    private string $baseUrl = 'https://graph.instagram.com/v23.0';

    public function getMe(string $accessToken): array
    {
        $response = Http::get(
            "{$this->baseUrl}/me",
            [
                'fields' => 'id,username',
                'access_token' => $accessToken,
            ]
        );

        if ($response->failed()) {
            throw new RuntimeException(
                'Instagram API failed: ' . $response->body()
            );
        }

        return $response->json();
    }

    public function getProfile(
        string $accessToken
    ): array {
        $response = Http::get(
            "{$this->baseUrl}/me",
            [
                'fields' => implode(',', [
                    'id',
                    'username',
                    'name',
                    'account_type',
                    'profile_picture_url',
                    'followers_count',
                    'follows_count',
                    'media_count',
                ]),
                'access_token' => $accessToken,
            ]
        );

        if ($response->failed()) {
            throw new RuntimeException(
                'Instagram profile request failed: '
                . $response->body()
            );
        }

        return $response->json();
    }

    public function getAccountInsights(
        string $accessToken,
        string $instagramId
    ): array {
        $metrics = [
            'reach',
            'profile_views',
            'views',
            'accounts_engaged',
            'total_interactions',
        ];

        $response = Http::withToken($accessToken)
            ->get(
                "{$this->baseUrl}/{$instagramId}/insights",
                [
                    'metric' => implode(',', $metrics),
                    'period' => 'day',
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Instagram insights request failed: '
                . $response->body()
            );
        }

        $responseData = $response->json('data', []);

        $metricsByName = [];

        foreach ($responseData as $metricData) {
            if (!isset($metricData['name'])) {
                continue;
            }

            $metricsByName[$metricData['name']] =
                $metricData;
        }

        $normalizedMetrics = [];

        foreach ($metrics as $metric) {
            $metricData =
                $metricsByName[$metric] ?? null;

            $history =
                is_array($metricData['values'] ?? null)
                    ? $metricData['values']
                    : [];

            $latest =
                $history !== []
                    ? $history[array_key_last($history)]
                    : null;

            $normalizedMetrics[$metric] = [
                'value' =>
                    is_array($latest)
                    && array_key_exists('value', $latest)
                        ? $latest['value']
                        : null,

                'has_data' =>
                    $history !== [],

                'history' =>
                    $history,
            ];
        }

        return [
            'period' => 'day',
            'metrics' => $normalizedMetrics,
        ];
    }

    public function getRecentMedia(
        string $accessToken,
        string $instagramId,
        int $limit = 12
    ): array {
        $response = Http::withToken($accessToken)
            ->get(
                "{$this->baseUrl}/{$instagramId}/media",
                [
                    'fields' => implode(',', [
                        'id',
                        'caption',
                        'media_type',
                        'media_url',
                        'permalink',
                        'thumbnail_url',
                        'timestamp',
                        'username',
                    ]),
                    'limit' => $limit,
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Instagram media request failed: '
                . $response->body()
            );
        }

        return [
            'data' => $response->json('data', []),
            'paging' => $response->json('paging'),
        ];
    }

    public function createImageContainer(
        string $accessToken,
        string $instagramId,
        string $imageUrl,
        ?string $caption = null
    ): array {
        $payload = [
            'image_url' => $imageUrl,
        ];

        if (
            is_string($caption)
            && trim($caption) !== ''
        ) {
            $payload['caption'] = $caption;
        }

        $response = Http::withToken($accessToken)
            ->asForm()
            ->post(
                "{$this->baseUrl}/{$instagramId}/media",
                $payload
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Instagram image container creation failed: '
                . $response->body()
            );
        }

        return $response->json();
    }

    public function publishContainer(
        string $accessToken,
        string $instagramId,
        string $creationId
    ): array {
        $response = Http::withToken($accessToken)
            ->asForm()
            ->post(
                "{$this->baseUrl}/{$instagramId}/media_publish",
                [
                    'creation_id' => $creationId,
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Instagram media publish failed: '
                . $response->body()
            );
        }

        return $response->json();
    }

    public function publishImage(
        string $accessToken,
        string $instagramId,
        string $imageUrl,
        ?string $caption = null
    ): array {
        $container = $this->createImageContainer(
            $accessToken,
            $instagramId,
            $imageUrl,
            $caption
        );

        $creationId = $container['id'] ?? null;

        if (
            !is_string($creationId)
            || trim($creationId) === ''
        ) {
            throw new RuntimeException(
                'Instagram did not return a media container ID.'
            );
        }

        $containerStatus = $this->getContainerStatus(
            $accessToken,
            $creationId
        );

        $statusCode = $containerStatus['status_code'] ?? null;

        if ($statusCode !== 'FINISHED') {
            return [
                'published' => false,
                'container_id' => $creationId,
                'media_id' => null,
                'status_code' => is_string($statusCode)
                    ? $statusCode
                    : null,
                'status' => $containerStatus['status'] ?? null,
            ];
        }

        $publishedMedia = $this->publishContainer(
            $accessToken,
            $instagramId,
            $creationId
        );

        return [
            'published' => true,
            'container_id' => $creationId,
            'media_id' => $publishedMedia['id'] ?? null,
            'status_code' => 'PUBLISHED',
            'status' => null,
        ];
    }

    public function getContainerStatus(
        string $accessToken,
        string $creationId
    ): array {
        $response = Http::withToken($accessToken)
            ->get(
                "{$this->baseUrl}/{$creationId}",
                [
                    'fields' => 'status_code,status',
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Instagram container status request failed: '
                . $response->body()
            );
        }


        return $response->json();
    }

    public function createReelContainer(
        string $accessToken,
        string $instagramId,
        string $videoUrl,
        ?string $caption = null,
        bool $shareToFeed = true,
    ): array {
        $payload = [
            'media_type' => 'REELS',
            'video_url' => $videoUrl,
            'share_to_feed' => $shareToFeed
                ? 'true'
                : 'false',
        ];

        if (
            is_string($caption)
            && trim($caption) !== ''
        ) {
            $payload['caption'] = $caption;
        }

        $response = Http::withToken($accessToken)
            ->asForm()
            ->post(
                "{$this->baseUrl}/{$instagramId}/media",
                $payload
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Instagram reel container creation failed: '
                . $response->body()
            );
        }

        return $response->json();
    }

    public function createStoryVideoContainer(
        string $accessToken,
        string $instagramId,
        string $videoUrl,
    ): array {
        $response = Http::withToken($accessToken)
            ->asForm()
            ->post(
                "{$this->baseUrl}/{$instagramId}/media",
                [
                    'media_type' => 'STORIES',
                    'video_url' => $videoUrl,
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Instagram story video container creation failed: '
                . $response->body()
            );
        }

        return $response->json();
    }

    public function createStoryImageContainer(
        string $accessToken,
        string $instagramId,
        string $imageUrl,
    ): array {
        $response = Http::withToken($accessToken)
            ->asForm()
            ->post(
                "{$this->baseUrl}/{$instagramId}/media",
                [
                    'media_type' => 'STORIES',
                    'image_url' => $imageUrl,
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Instagram story image container creation failed: '
                . $response->body()
            );
        }

        return $response->json();
    }

    public function getMediaComments(
        string $accessToken,
        string $mediaId,
        int $limit = 50,
    ): array {
        $response = Http::withToken($accessToken)
            ->get(
                "{$this->baseUrl}/{$mediaId}/comments",
                [
                    'fields' => implode(',', [
                        'id',
                        'from',
                        'text',
                        'timestamp',
                    ]),
                    'limit' => $limit,
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Instagram comments request failed: '
                . $response->body()
            );
        }

        return [
            'data' => $response->json('data', []),
            'paging' => $response->json('paging'),
        ];
    }

    public function replyToComment(
        string $accessToken,
        string $commentId,
        string $message,
    ): array {
        $response = Http::withToken($accessToken)
            ->asForm()
            ->post(
                "{$this->baseUrl}/{$commentId}/replies",
                [
                    'message' => $message,
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Instagram comment reply failed: '
                . $response->body()
            );
        }

        return $response->json();
    }

    public function setCommentHidden(
        string $accessToken,
        string $commentId,
        bool $hidden,
    ): array {
        $response = Http::withToken($accessToken)
            ->asForm()
            ->post(
                "{$this->baseUrl}/{$commentId}",
                [
                    'hide' => $hidden ? 'true' : 'false',
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Instagram comment visibility update failed: '
                . $response->body()
            );
        }

        return $response->json();
    }

    public function hideComment(
        string $accessToken,
        string $commentId,
    ): array {
        return $this->setCommentHidden(
            $accessToken,
            $commentId,
            true,
        );
    }

    public function unhideComment(
        string $accessToken,
        string $commentId,
    ): array {
        return $this->setCommentHidden(
            $accessToken,
            $commentId,
            false,
        );
    }

    public function deleteComment(
        string $accessToken,
        string $commentId,
    ): array {
        $response = Http::withToken($accessToken)
            ->delete(
                "{$this->baseUrl}/{$commentId}"
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Instagram comment deletion failed: '
                . $response->body()
            );
        }

        return $response->json();
    }
}
