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

    private const FEED_MIN_ASPECT_RATIO = 4 / 5;

    private const FEED_MAX_ASPECT_RATIO = 1.91;

    private const JPEG_QUALITY = 92;

    /**
     * Store an Instagram image without changing
     * its aspect ratio.
     *
     * Used for Story images.
     *
     * PNG and WebP are converted to JPEG.
     *
     * @return array{path: string, url: string}
     */
    public function storeImage(
        UploadedFile $image
    ): array {
        return $this->storeProcessedImage(
            $image,
            normalizeFeedAspectRatio: false,
        );
    }

    /**
     * Store an Instagram Feed image.
     *
     * Images outside Instagram Feed's supported
     * aspect-ratio range are padded without cropping.
     *
     * PNG and WebP are converted to JPEG.
     *
     * @return array{path: string, url: string}
     */
    public function storeFeedImage(
        UploadedFile $image
    ): array {
        return $this->storeProcessedImage(
            $image,
            normalizeFeedAspectRatio: true,
        );
    }

    /**
     * @return array{path: string, url: string}
     */
    private function storeProcessedImage(
        UploadedFile $image,
        bool $normalizeFeedAspectRatio,
    ): array {
        $mimeType = $image->getMimeType();

        if (!in_array(
            $mimeType,
            [
                'image/jpeg',
                'image/png',
                'image/webp',
            ],
            true,
        )) {
            throw new RuntimeException(
                'Unsupported Instagram publishing image format.'
            );
        }

        $filename =
            Str::uuid()->toString()
            . '.jpg';

        /*
         * For a normal JPEG that does not need Feed
         * normalization, keep the original file.
         *
         * This avoids unnecessary JPEG re-encoding
         * for Story images.
         */
        if (
            !$normalizeFeedAspectRatio
            && $mimeType === 'image/jpeg'
        ) {
            return $this->storeOriginalJpeg(
                $image,
                $filename,
            );
        }

        $sourcePath = $image->getRealPath();

        if (
            !is_string($sourcePath)
            || $sourcePath === ''
        ) {
            throw new RuntimeException(
                'Instagram publishing image source is unavailable.'
            );
        }

        $sourceData = file_get_contents(
            $sourcePath
        );

        if ($sourceData === false) {
            throw new RuntimeException(
                'Failed to read Instagram publishing image.'
            );
        }

        $sourceImage = imagecreatefromstring(
            $sourceData
        );

        if ($sourceImage === false) {
            throw new RuntimeException(
                'Failed to decode Instagram publishing image.'
            );
        }

        /*
         * JPEG photos from phones and cameras may
         * store their visible rotation in EXIF
         * instead of rotating the actual pixels.
         *
         * Because Feed images may be re-encoded,
         * apply that orientation before continuing.
         */
        if ($mimeType === 'image/jpeg') {
            $sourceImage = $this->applyJpegOrientation(
                $sourceImage,
                $sourcePath,
            );
        }

        $width = imagesx(
            $sourceImage
        );

        $height = imagesy(
            $sourceImage
        );

        if (
            $width <= 0
            || $height <= 0
        ) {
            imagedestroy(
                $sourceImage
            );

            throw new RuntimeException(
                'Instagram publishing image dimensions are invalid.'
            );
        }

        $targetWidth = $width;
        $targetHeight = $height;

        if ($normalizeFeedAspectRatio) {
            [
                $targetWidth,
                $targetHeight,
            ] = $this->calculateFeedCanvasSize(
                $width,
                $height,
            );
        }

        $jpegImage = imagecreatetruecolor(
            $targetWidth,
            $targetHeight
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
         * JPEG has no transparency.
         *
         * Transparent PNG/WebP pixels and any padding
         * required for Feed aspect ratio are rendered
         * on a white background.
         */
        $white = imagecolorallocate(
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

        imagealphablending(
            $jpegImage,
            true
        );

        $offsetX = (int) floor(
            ($targetWidth - $width) / 2
        );

        $offsetY = (int) floor(
            ($targetHeight - $height) / 2
        );

        imagecopy(
            $jpegImage,
            $sourceImage,
            $offsetX,
            $offsetY,
            0,
            0,
            $width,
            $height
        );

        ob_start();

        $encoded = imagejpeg(
            $jpegImage,
            null,
            self::JPEG_QUALITY
        );

        $jpegData = ob_get_clean();

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

        $stored = Storage::disk(
            self::DISK
        )->put(
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
                Storage::disk(
                    self::DISK
                )->url(
                    $filename
                ),
        ];
    }

    /**
     * @return array{path: string, url: string}
     */
    private function storeOriginalJpeg(
        UploadedFile $image,
        string $filename,
    ): array {
        $path = Storage::disk(
            self::DISK
        )->putFileAs(
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
                Storage::disk(
                    self::DISK
                )->url(
                    $path
                ),
        ];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function calculateFeedCanvasSize(
        int $width,
        int $height,
    ): array {
        $aspectRatio =
            $width / $height;

        /*
         * Too tall:
         *
         * Add space to the left and right until
         * the image reaches 4:5.
         */
        if (
            $aspectRatio
            < self::FEED_MIN_ASPECT_RATIO
        ) {
            $targetWidth = (int) ceil(
                $height
                * self::FEED_MIN_ASPECT_RATIO
            );

            return [
                $targetWidth,
                $height,
            ];
        }

        /*
         * Too wide:
         *
         * Add space above and below until
         * the image reaches 1.91:1.
         */
        if (
            $aspectRatio
            > self::FEED_MAX_ASPECT_RATIO
        ) {
            $targetHeight = (int) ceil(
                $width
                / self::FEED_MAX_ASPECT_RATIO
            );

            return [
                $width,
                $targetHeight,
            ];
        }

        /*
         * Already inside Instagram Feed's
         * supported aspect-ratio range.
         */
        return [
            $width,
            $height,
        ];
    }

    /**
     * Apply JPEG EXIF orientation before
     * re-encoding the image.
     *
     * @param \GdImage $image
     */
    private function applyJpegOrientation(
        \GdImage $image,
        string $sourcePath,
    ): \GdImage {
        if (!function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data(
            $sourcePath
        );

        if (!is_array($exif)) {
            return $image;
        }

        $orientation = (int) (
            $exif['Orientation'] ?? 1
        );

        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate(
            $image,
            $angle,
            0
        );

        if ($rotated === false) {
            return $image;
        }

        imagedestroy(
            $image
        );

        return $rotated;
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

        if (!in_array(
            $extension,
            [
                'mp4',
                'mov',
            ],
            true,
        )) {
            throw new RuntimeException(
                'Unsupported Instagram publishing video format.'
            );
        }

        $filename =
            Str::uuid()->toString()
            . '.'
            . $extension;

        $path = Storage::disk(
            self::DISK
        )->putFileAs(
            '',
            $video,
            $filename
        );

        if (
            !is_string($path)
            || $path === ''
        ) {
            throw new RuntimeException(
                'Failed to store Instagram publishing video.'
            );
        }

        return [
            'path' => $path,
            'url' =>
                Storage::disk(
                    self::DISK
                )->url(
                    $path
                ),
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

        return Storage::disk(
            self::DISK
        )->url(
            $path
        );
    }

    public function delete(
        string $path
    ): void {
        if ($path === '') {
            return;
        }

        Storage::disk(
            self::DISK
        )->delete(
            $path
        );
    }
}
