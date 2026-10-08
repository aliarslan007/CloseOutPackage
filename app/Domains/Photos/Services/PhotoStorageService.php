<?php

declare(strict_types=1);

namespace App\Domains\Photos\Services;

use App\Domains\Photos\Models\Photo;
use App\Shared\Exceptions\HeicConversionFailedException;
use App\Shared\Exceptions\PhotoStorageException;
use App\Shared\Services\ImageCompressionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

final class PhotoStorageService
{
    private const HEIC_MIME_TYPES = ['image/heic', 'image/heif'];

    public function __construct(private readonly ImageCompressionService $imageCompression) {}

    public function store(Photo $photo, UploadedFile $file): Media
    {
        try {
            $file = $this->convertHeicIfNeeded($file);
            $file = $this->imageCompression->compressUploadedFile($file);

            return $photo->addMedia($file)->toMediaCollection(Photo::COLLECTION);
        } catch (HeicConversionFailedException|PhotoStorageException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Photo storage failed', [
                'photo_id' => $photo->id,
                'error' => $exception->getMessage(),
            ]);

            throw new PhotoStorageException('Unable to store the photo. Please try again.');
        }
    }

    private function convertHeicIfNeeded(UploadedFile $file): UploadedFile
    {
        $mime = $file->getMimeType();

        if (! in_array($mime, self::HEIC_MIME_TYPES, true)) {
            return $file;
        }

        try {
            $targetPath = tempnam(sys_get_temp_dir(), 'cop_photo_').'.jpg';

            ImageManager::gd()
                ->read($file->getRealPath())
                ->toJpeg(90)
                ->save($targetPath);

            return new UploadedFile(
                $targetPath,
                $file->getClientOriginalName().'.jpg',
                'image/jpeg',
                null,
                true,
            );
        } catch (Throwable $exception) {
            Log::error('HEIC conversion failed', [
                'file' => $file->getClientOriginalName(),
                'error' => $exception->getMessage(),
            ]);

            throw new HeicConversionFailedException('This HEIC photo could not be processed. Please upload a JPEG or PNG instead.');
        }
    }
}
