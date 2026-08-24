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

        $publishedMedia = $this->publishContainer(
            $accessToken,
            $instagramId,
            $creationId
        );

        return [
            'container_id' => $creationId,
            'media_id' => $publishedMedia['id'] ?? null,
        ];
    }
}
