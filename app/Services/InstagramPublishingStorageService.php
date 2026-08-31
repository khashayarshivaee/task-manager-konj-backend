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
    /**
     * @return array{path: string, url: string}
     */
    public function storeImage(
        UploadedFile $image
    ): array {
        $mimeType = $image->getMimeType();

        if (!in_array(
            $mimeType,
            [
                'image/jpeg',
                'image/png',
                'image/webp',
            ],
            true
        )) {
            throw new RuntimeException(
                'Unsupported Instagram publishing image format.'
            );
        }

        $filename =
            Str::uuid()->toString()
            . '.jpg';

        if ($mimeType === 'image/jpeg') {
            $path = Storage::disk(self::DISK)
                ->putFileAs(
                    '',
                    $image,
                    $filename
                );

            if (
                !is_string($path)
                || $path === ''
            ) {
                throw new RuntimeException(
                    'Failed to store Instagram publishing image.'
                );
            }

            return [
                'path' => $path,
                'url' =>
                    Storage::disk(self::DISK)
                        ->url($path),
            ];
        }

        $sourcePath =
            $image->getRealPath();

        if (
            !is_string($sourcePath)
            || $sourcePath === ''
        ) {
            throw new RuntimeException(
                'Instagram publishing image source is unavailable.'
            );
        }

        $sourceData =
            file_get_contents(
                $sourcePath
            );

        if ($sourceData === false) {
            throw new RuntimeException(
                'Failed to read Instagram publishing image.'
            );
        }

        $sourceImage =
            imagecreatefromstring(
                $sourceData
            );

        if ($sourceImage === false) {
            throw new RuntimeException(
                'Failed to decode Instagram publishing image.'
            );
        }

        $width =
            imagesx($sourceImage);

        $height =
            imagesy($sourceImage);

        $jpegImage =
            imagecreatetruecolor(
                $width,
                $height
            );

        if ($jpegImage === false) {
            imagedestroy(
                $sourceImage
            );

            throw new RuntimeException(
                'Failed to create Instagram JPEG image.'
            );
        }

        /*
         * PNG / WebP may contain transparency.
         * Instagram JPEG does not, so use white
         * as the background.
         */
        $white =
            imagecolorallocate(
                $jpegImage,
                255,
                255,
                255
            );

        imagefill(
            $jpegImage,
            0,
            0,
            $white
        );

        imagecopy(
            $jpegImage,
            $sourceImage,
            0,
            0,
            0,
            0,
            $width,
            $height
        );

        ob_start();

        $encoded =
            imagejpeg(
                $jpegImage,
                null,
                92
            );

        $jpegData =
            ob_get_clean();

        imagedestroy(
            $sourceImage
        );

        imagedestroy(
            $jpegImage
        );

        if (
            !$encoded
            || !is_string($jpegData)
            || $jpegData === ''
        ) {
            throw new RuntimeException(
                'Failed to encode Instagram publishing image as JPEG.'
            );
        }

        $stored =
            Storage::disk(self::DISK)
                ->put(
                    $filename,
                    $jpegData
                );

        if (!$stored) {
            throw new RuntimeException(
                'Failed to store Instagram publishing image.'
            );
        }

        return [
            'path' => $filename,
            'url' =>
                Storage::disk(self::DISK)
                    ->url($filename),
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
