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

    /**
     * @return array{path: string, url: string}
     */
    public function storeVideo(
        UploadedFile $video
    ): array {
        $extension = strtolower(
            $video->getClientOriginalExtension()
        );

        if (!in_array($extension, ['mp4', 'mov'], true)) {
            throw new RuntimeException(
                'Unsupported Instagram publishing video format.'
            );
        }

        $filename = Str::uuid()->toString()
            . '.'
            . $extension;

        $path = Storage::disk(self::DISK)->putFileAs(
            '',
            $video,
            $filename
        );

        if (!is_string($path) || $path === '') {
            throw new RuntimeException(
                'Failed to store Instagram publishing video.'
            );
        }

        return [
            'path' => $path,
            'url' => Storage::disk(self::DISK)->url($path),
        ];
    }

    public function url(
        string $path
    ): string {
        if (trim($path) === '') {
            throw new RuntimeException(
                'Instagram publishing staging path is unavailable.'
            );
        }

        return Storage::disk(self::DISK)->url($path);
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
