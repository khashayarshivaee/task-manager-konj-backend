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
}
