<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Services;

use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Creates a size-capped JPEG copy of a source photo in the system temp
 * directory so Chromium never has to decode full-resolution originals.
 * Used at render time by the legacy path and per-chunk by the chunked path.
 *
 * HEIC policy: uploads are converted HEIC→JPEG at upload time
 * (PhotoStorageService / MediaAssetValidationService), so by the time a file
 * reaches the PDF pipeline it must already be JPEG/PNG/WebP-readable. The
 * `copy()` fallback below exists only for exotic-but-decodable formats —
 * never as a HEIC path (a HEIC original copied verbatim would silently render
 * as a broken image in Chromium).
 */
final class PdfPhotoOptimizer
{
    public const MAX_DIMENSION = 1200;

    public const JPEG_QUALITY = 80;

    /**
     * @return string|null absolute path to the optimized temp copy, or null
     *                     when the source could not be prepared
     */
    public function optimizeCopy(string $source): ?string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'cop_photo_');

        if ($tmpPath === false) {
            return null;
        }

        try {
            $image = ImageManager::gd()->read($source);
            $image->scaleDown(self::MAX_DIMENSION, self::MAX_DIMENSION);

            file_put_contents($tmpPath, (string) $image->toJpeg(self::JPEG_QUALITY));

            return $tmpPath;
        } catch (Throwable) {
            // Unsupported format or processing failure: fall back to the original file.
        }

        if (copy($source, $tmpPath)) {
            return $tmpPath;
        }

        @unlink($tmpPath);
        Log::warning('PDF photo could not be optimized', ['source' => $source]);

        return null;
    }
}
