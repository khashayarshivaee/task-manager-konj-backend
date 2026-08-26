<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class InstagramMediaProxyController extends Controller
{
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'url' => [
                'required',
                'url',
            ],
        ]);

        $url = $validated['url'];

        $host = parse_url(
            $url,
            PHP_URL_HOST,
        );

        if (
            !is_string($host)
            || !$this->isAllowedHost($host)
        ) {
            abort(
                403,
                'Instagram media host is not allowed.',
            );
        }

        $response = Http::timeout(20)
            ->retry(
                2,
                500,
            )
            ->withHeaders([
                'User-Agent' =>
                    'Mozilla/5.0',
            ])
            ->get($url);

        if (!$response->successful()) {
            abort(
                502,
                'Unable to load Instagram media.',
            );
        }

        return response(
            $response->body(),
            200,
        )
            ->header(
                'Content-Type',
                $response->header(
                    'Content-Type',
                ) ?? 'image/jpeg',
            )
            ->header(
                'Cache-Control',
                'public, max-age=300',
            );
    }

    private function isAllowedHost(
        string $host,
    ): bool {
        $host = strtolower(
            trim($host),
        );

        return
            $host === 'cdninstagram.com'
            || $host === 'fbcdn.net'
            || Str::endsWith(
                $host,
                [
                    '.cdninstagram.com',
                    '.fbcdn.net',
                ],
            );
    }
}
