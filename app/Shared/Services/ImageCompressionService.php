<?php

declare(strict_types=1);

namespace App\Shared\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncodedImageInterface;
use Intervention\Image\Interfaces\ImageInterface;
use Throwable;

/**
 * Server-side backstop for COP photo paths.
 *
 * Client-side canvas compression (resources/js/services/compression.service.ts)
 * silently falls back to the original file when it cannot decode (for example a
 * HEIC capture on an older browser), so an oversized phone photo can still reach
 * the server. This service downscales and re-encodes those files before storage.
 *
 * Scope: closeout-package *duplicate* copies only — device/camera uploads, site
 * notes, and copies duplicated out of the Media Library into COP or Change
 * Orders. The Media Library master asset is never touched (byte-for-byte DAM).
 *
 * Fail-safe by contract: any decode/encode error returns the original
 * file/path and logs a warning — an upload must never be blocked.
 */
final class ImageCompressionService
{
    private const GIF_MIME = 'image/gif';

    private const WEBP_MIME = 'image/webp';

    private const PNG_MIME = 'image/png';

    /**
     * Compress an in-flight upload. Returns the original when no work is
     * needed or compression fails.
     */
    public function compressUploadedFile(UploadedFile $file): UploadedFile
    {
        try {
            $path = $file->getRealPath();

            if ($path === false) {
                return $file;
            }

            $target = $this->compressPath(
                $path,
                $file->getMimeType() ?? '',
                (int) $file->getSize(),
            );

            if ($target === null) {
                return $file;
            }

            $extension = pathinfo($target, PATHINFO_EXTENSION) ?: 'jpg';

            return new UploadedFile(
                $target,
                pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME).'.'.$extension,
                $this->mimeFor($extension),
                null,
                true,
            );
        } catch (Throwable $exception) {
            Log::warning('Image compression skipped', [
                'file' => $file->getClientOriginalName(),
                'error' => $exception->getMessage(),
            ]);

            return $file;
        }
    }

    /**
     * Compress a file already on disk (e.g. a Media Library master being
     * duplicated into a Closeout Package or Change Order copy). Returns the
     * path of a compressed temp copy, or the original path when no work is
     * needed or compression fails (fail-safe).
     *
     * @param  string  $mimeType  trusted server-side MIME (finfo or the media
     *                            record's stored type — never a client claim)
     */
    public function compressFilePath(string $sourcePath, string $mimeType, ?string $originalName = null): string
    {
        try {
            $bytes = filesize($sourcePath);

            return $this->compressPath($sourcePath, $mimeType, $bytes === false ? 0 : $bytes)
                ?? $sourcePath;
        } catch (Throwable $exception) {
            Log::warning('Image compression skipped', [
                'file' => $originalName ?? basename($sourcePath),
                'error' => $exception->getMessage(),
            ]);

            return $sourcePath;
        }
    }

    /**
     * Shared decision logic. Returns null when the file should be kept as-is,
     * otherwise the path of a new compressed temp file.
     */
    private function compressPath(string $path, string $mime, int $bytes): ?string
    {
        // Non-images (e.g. video attachments on project issues) and animated GIFs
        // must not enter the single-frame GD image pipeline.
        if (! str_starts_with($mime, 'image/') || $mime === self::GIF_MIME) {
            return null;
        }

        $maxDimension = config()->integer('cop.image.max_dimension', 2560);

        // Kill switch: 0 disables server-side processing entirely, so ops can
        // bypass the GD decoder without a deploy if a rare format trips it.
        if ($maxDimension <= 0) {
            return null;
        }

        $thresholdBytes = config()->integer('cop.image.recompress_threshold_mb', 3) * 1024 * 1024;
        $overWeight = $bytes > $thresholdBytes;
        $overDimension = $this->longestSide($path) > $maxDimension;

        // Already small enough and light enough: keep untouched (no double compression).
        if (! $overWeight && ! $overDimension) {
            return null;
        }

        $image = ImageManager::gd()->read($path);

        if ($overDimension) {
            $image->scaleDown($maxDimension, $maxDimension);
        }

        $targetPath = tempnam(sys_get_temp_dir(), 'cop_compress_');

        if ($targetPath === false) {
            return null;
        }

        // tempnam() already created a 0-byte file at $targetPath; remove it
        // before writing the extension-suffixed name so no orphan inode is
        // left behind during high-volume upload batches.
        @unlink($targetPath);
        $extension = $this->extensionFor($mime);
        $targetPath .= '.'.$extension;

        $quality = config()->integer('cop.image.jpeg_quality', 85);
        file_put_contents($targetPath, (string) $this->encode($image, $mime, $quality));

        return $targetPath;
    }

    private function longestSide(string $path): int
    {
        $dimensions = @getimagesize($path);

        if ($dimensions === false) {
            return 0;
        }

        return max($dimensions[0], $dimensions[1]);
    }

    private function encode(ImageInterface $image, string $mime, int $quality): EncodedImageInterface
    {
        return match ($mime) {
            // PNG: downscale only — no quality loss or alpha flattening.
            self::PNG_MIME => $image->toPng(),
            // Re-encode in kind so the MIME type never changes silently.
            self::WEBP_MIME => $image->toWebp($quality),
            default => $image->toJpeg($quality),
        };
    }

    private function extensionFor(string $mime): string
    {
        return match ($mime) {
            self::PNG_MIME => 'png',
            self::WEBP_MIME => 'webp',
            default => 'jpg',
        };
    }

    private function mimeFor(string $extension): string
    {
        return match ($extension) {
            'png' => self::PNG_MIME,
            'webp' => self::WEBP_MIME,
            default => 'image/jpeg',
        };
    }
}
