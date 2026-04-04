<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use MohamedGaldi\ViltFilepond\Models\TempFile;

class ContractCustomerPhotoExtractor
{
    public function extractToTempFolder(string $sourcePath, ?string $mimeType, ?string $documentType = null, string $diskName = 'public'): ?array
    {
        if (!extension_loaded('gd')) {
            return null;
        }

        $disk = Storage::disk($diskName);
        $normalizedSourcePath = ltrim($sourcePath, '/');
        if (! $disk->exists($normalizedSourcePath)) {
            return null;
        }

        $absolutePath = $disk->path($normalizedSourcePath);
        $imageInfo = @getimagesize($absolutePath);
        if ($imageInfo === false) {
            return null;
        }

        [$sourceWidth, $sourceHeight, $imageType] = $imageInfo;
        if ($sourceWidth < 1 || $sourceHeight < 1) {
            return null;
        }

        $sourceImage = $this->createSourceImage($absolutePath, $imageType);
        if (! $sourceImage) {
            return null;
        }

        [$cropX, $cropY, $cropWidth, $cropHeight] = $this->resolveCropBox($sourceWidth, $sourceHeight, (string) $documentType);
        $cropped = imagecreatetruecolor($cropWidth, $cropHeight);
        imagealphablending($cropped, false);
        imagesavealpha($cropped, true);
        $transparent = imagecolorallocatealpha($cropped, 255, 255, 255, 127);
        imagefilledrectangle($cropped, 0, 0, $cropWidth, $cropHeight, $transparent);

        imagecopyresampled(
            $cropped,
            $sourceImage,
            0,
            0,
            $cropX,
            $cropY,
            $cropWidth,
            $cropHeight,
            $cropWidth,
            $cropHeight
        );

        $folder = (string) Str::uuid();
        $extension = $this->extensionForImageType($imageType);
        $filename = 'customer-photo'.($extension !== '' ? '.'.$extension : '');
        $relativePath = trim((string) config('vilt-filepond.temp_path', 'temp-files'), '/').'/'.$folder.'/'.$filename;

        $disk->makeDirectory(trim((string) config('vilt-filepond.temp_path', 'temp-files'), '/').'/'.$folder);
        $targetAbsolutePath = $disk->path($relativePath);
        $this->writeImage($cropped, $targetAbsolutePath, $imageType);

        imagedestroy($sourceImage);
        imagedestroy($cropped);
        clearstatcache(true, $targetAbsolutePath);

        $size = is_file($targetAbsolutePath) ? (filesize($targetAbsolutePath) ?: 0) : 0;
        if ($size <= 0) {
            return null;
        }

        $mime = image_type_to_mime_type($imageType) ?: ($mimeType ?: 'image/png');

        $temp = TempFile::query()->create([
            'original_name' => $filename,
            'filename' => $filename,
            'path' => $relativePath,
            'mime_type' => $mime,
            'size' => $size,
            'folder' => $folder,
            'is_chunked' => false,
        ]);

        return [
            'folder' => $temp->folder,
            'url' => $disk->url($relativePath),
            'mime_type' => $mime,
            'filename' => $filename,
        ];
    }

    private function resolveCropBox(int $width, int $height, string $documentType): array
    {
        $ratios = match ($documentType) {
            'passport' => ['x' => 0.06, 'y' => 0.18, 'w' => 0.30, 'h' => 0.48],
            'driver_license' => ['x' => 0.04, 'y' => 0.10, 'w' => 0.34, 'h' => 0.54],
            'residency_card' => ['x' => 0.04, 'y' => 0.08, 'w' => 0.34, 'h' => 0.56],
            default => ['x' => 0.04, 'y' => 0.08, 'w' => 0.34, 'h' => 0.56],
        };

        $x = max(0, (int) round($width * $ratios['x']));
        $y = max(0, (int) round($height * $ratios['y']));
        $cropWidth = max(1, (int) round($width * $ratios['w']));
        $cropHeight = max(1, (int) round($height * $ratios['h']));

        if ($x + $cropWidth > $width) {
            $cropWidth = $width - $x;
        }

        if ($y + $cropHeight > $height) {
            $cropHeight = $height - $y;
        }

        return [$x, $y, max(1, $cropWidth), max(1, $cropHeight)];
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

    private function writeImage(mixed $canvas, string $absolutePath, int $imageType): void
    {
        match ($imageType) {
            IMAGETYPE_JPEG => imagejpeg($canvas, $absolutePath, 90),
            IMAGETYPE_PNG => imagepng($canvas, $absolutePath, 6),
            IMAGETYPE_WEBP => function_exists('imagewebp') ? imagewebp($canvas, $absolutePath, 90) : imagepng($canvas, $absolutePath, 6),
            IMAGETYPE_GIF => imagegif($canvas, $absolutePath),
            default => imagepng($canvas, $absolutePath, 6),
        };
    }

    private function extensionForImageType(int $imageType): string
    {
        return match ($imageType) {
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
            IMAGETYPE_GIF => 'gif',
            default => 'png',
        };
    }
}
