<?php

use App\Domains\MediaLibrary\Services\MediaAssetValidationService;
use App\Shared\Exceptions\InvalidMediaFileException;
use Illuminate\Http\UploadedFile;

it('accepts a real JPEG that matches its declaration', function (): void {
    $file = UploadedFile::fake()->image('photo.jpg', 100, 100);
    $result = app(MediaAssetValidationService::class)->assertValidImage((string) $file->getRealPath(), 'image/jpeg');

    expect($result['mime_type'])->toBe('image/jpeg');
});

it('rejects a file whose magic bytes are not an allowed image', function (): void {
    $temp = tempnam(sys_get_temp_dir(), 'media_bad_');
    file_put_contents($temp, 'This is definitely not an image.');

    app(MediaAssetValidationService::class)->assertValidImage($temp, 'image/jpeg');
})->throws(InvalidMediaFileException::class, 'This file appears corrupt or is not a supported image. Try re-uploading.');

it('rejects a file whose detected type differs from the declaration', function (): void {
    // PNG bytes (finfo sees image/png) declared as JPEG — a spoofed upload.
    $png = UploadedFile::fake()->image('photo.png', 100, 100);

    app(MediaAssetValidationService::class)->assertValidImage((string) $png->getRealPath(), 'image/jpeg');
})->throws(InvalidMediaFileException::class);
