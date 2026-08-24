<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class InstagramPublishingStorageService
{
    private const DISK = 'instagram_publishing';

    /**
     * @return array{path: string, url: string}
     */
    public function storeImage(
        UploadedFile $image
    ): array {
        $filename = Str::uuid()->toString() . '.jpg';

        $path = Storage::disk(self::DISK)->putFileAs(
            '',
            $image,
            $filename
        );

        if (!is_string($path) || $path === '') {
            throw new RuntimeException(
                'Failed to store Instagram publishing image.'
            );
        }

        return [
            'path' => $path,
            'url' => Storage::disk(self::DISK)->url($path),
        ];
    }

    public function delete(
        string $path
    ): void {
        if ($path === '') {
            return;
        }

        Storage::disk(self::DISK)->delete($path);
    }
}
