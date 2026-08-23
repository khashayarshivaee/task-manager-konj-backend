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
}
