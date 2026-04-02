<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use MohamedGaldi\ViltFilepond\Models\File;

class BrandLogoImageResizer
{
    public const TARGET_WIDTH = 491;
    public const TARGET_HEIGHT = 200;

    public function resize(File $file, int $targetWidth, int $targetHeight): void
    {
        if (!extension_loaded('gd')) {
            return;
        }

        $disk = Storage::disk(config('vilt-filepond.storage_disk'));
        $path = $file->getCleanPath();

        if (!$disk->exists($path)) {
            return;
        }

        $absolutePath = $disk->path($path);
        $imageInfo = @getimagesize($absolutePath);

        if ($imageInfo === false) {
            return;
        }

        [$sourceWidth, $sourceHeight, $imageType] = $imageInfo;

        if ($sourceWidth < 1 || $sourceHeight < 1) {
            return;
        }

        $source = $this->createSourceImage($absolutePath, $imageType);

        if (!$source) {
            return;
        }

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);

        $scale = min($targetWidth / $sourceWidth, $targetHeight / $sourceHeight);
        $scaledWidth = max(1, (int) round($sourceWidth * $scale));
        $scaledHeight = max(1, (int) round($sourceHeight * $scale));
        $destinationX = (int) floor(($targetWidth - $scaledWidth) / 2);
        $destinationY = (int) floor(($targetHeight - $scaledHeight) / 2);

        imagecopyresampled(
            $canvas,
            $source,
            $destinationX,
            $destinationY,
            0,
            0,
            $scaledWidth,
            $scaledHeight,
            $sourceWidth,
            $sourceHeight
        );

        $this->writeResizedImage($canvas, $absolutePath, $imageType);

        imagedestroy($source);
        imagedestroy($canvas);

        clearstatcache(true, $absolutePath);

        $file->forceFill([
            'size' => filesize($absolutePath) ?: $file->size,
            'mime_type' => image_type_to_mime_type($imageType) ?: $file->mime_type,
        ])->save();
    }

    private function createSourceImage(string $absolutePath, int $imageType): mixed
    {
        return match ($imageType) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($absolutePath),
            IMAGETYPE_PNG => @imagecreatefrompng($absolutePath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolutePath) : false,
            IMAGETYPE_GIF => @imagecreatefromgif($absolutePath),
            default => false,
        };
    }

    private function writeResizedImage(mixed $canvas, string $absolutePath, int $imageType): void
    {
        match ($imageType) {
            IMAGETYPE_JPEG => imagejpeg($canvas, $absolutePath, 90),
            IMAGETYPE_PNG => imagepng($canvas, $absolutePath, 6),
            IMAGETYPE_WEBP => function_exists('imagewebp')
                ? imagewebp($canvas, $absolutePath, 90)
                : imagepng($canvas, $absolutePath, 6),
            IMAGETYPE_GIF => imagegif($canvas, $absolutePath),
            default => null,
        };
    }
}
