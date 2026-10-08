<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Services;

use App\Shared\Exceptions\HeicConversionFailedException;
use App\Shared\Exceptions\InvalidMediaFileException;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Deep validation of a reassembled upload: magic bytes must identify a
 * supported image whose type matches the declaration, and the file must
 * actually decode as an image.
 *
 * HEIC/HEIF is accepted but converted to JPEG (GD cannot generate the thumb
 * conversions from HEIC) — mirroring the photo upload flow.
 */
final class MediaAssetValidationService
{
    private const HEIC_MIME_TYPES = ['image/heic', 'image/heif'];

    private const CORRUPT_MESSAGE = 'This file appears corrupt or is not a supported image. Try re-uploading.';

    /**
     * @return array{path: string, mime_type: string} The validated file path
     *                                                and its effective MIME type (HEIC/HEIF yields image/jpeg).
     *
     * @throws InvalidMediaFileException
     * @throws HeicConversionFailedException
     */
    public function assertValidImage(string $path, string $declaredMime): array
    {
        $detected = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $detected = is_string($detected) && $detected !== '' ? $detected : null;

        if ($detected !== null) {
            $allowedMimes = $this->allowedMimes();

            // Real content must be a supported image…
            if (! in_array($detected, $allowedMimes, true)) {
                throw new InvalidMediaFileException(self::CORRUPT_MESSAGE);
            }

            // …and match the declared type (blocks extension/MIME spoofing).
            // HEIC is exempt: finfo reporting varies, and it is converted below.
            if (! $this->isHeic($declaredMime) && $detected !== $declaredMime) {
                throw new InvalidMediaFileException(self::CORRUPT_MESSAGE);
            }
        }

        if ($this->isHeic($declaredMime)) {
            return $this->convertHeicToJpeg($path);
        }

        try {
            ImageManager::gd()->read($path);
        } catch (Throwable $exception) {
            Log::error('Media asset failed image decode', [
                'path' => $path,
                'mime' => $declaredMime,
                'error' => $exception->getMessage(),
            ]);

            throw new InvalidMediaFileException(self::CORRUPT_MESSAGE);
        }

        return ['path' => $path, 'mime_type' => $declaredMime];
    }

    /**
     * @return array{path: string, mime_type: string}
     *
     * @throws HeicConversionFailedException
     */
    private function convertHeicToJpeg(string $path): array
    {
        try {
            $targetPath = sys_get_temp_dir().'/cop_media_'.uniqid('', true).'.jpg';

            ImageManager::gd()
                ->read($path)
                ->toJpeg(90)
                ->save($targetPath);

            return ['path' => $targetPath, 'mime_type' => 'image/jpeg'];
        } catch (Throwable $exception) {
            Log::error('HEIC media conversion failed', [
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);

            throw new HeicConversionFailedException(
                'This HEIC photo could not be processed. Please upload a JPEG or PNG instead.',
            );
        }
    }

    private function isHeic(string $mimeType): bool
    {
        return in_array($mimeType, self::HEIC_MIME_TYPES, true);
    }

    /**
     * @return list<string>
     */
    private function allowedMimes(): array
    {
        /** @var mixed $mimes */
        $mimes = config('cop.media_allowed_mimes', []);

        return is_array($mimes)
            ? array_values(array_filter($mimes, 'is_string'))
            : [];
    }
}
