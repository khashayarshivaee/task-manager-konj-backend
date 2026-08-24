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
        $response = Http::withToken($accessToken)
            ->get(
                "{$this->baseUrl}/{$instagramId}/insights",
                [
                    'metric' => implode(',', [
                        'reach',
                        'profile_views',
                    ]),
                    'period' => 'day',
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Instagram insights request failed: '
                . $response->body()
            );
        }

        return $response->json();
    }
}
