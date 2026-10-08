<?php

use App\Shared\Services\ImageCompressionService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

uses(TestCase::class);

it('downscales an oversized JPEG and keeps the JPEG mime', function (): void {
    $result = app(ImageCompressionService::class)->compressUploadedFile(copTestImage('image/jpeg', 3000, 2000));

    expect($result->getMimeType())->toBe('image/jpeg')
        ->and(copTestImageDimension((string) $result->getRealPath()))->toBeLessThanOrEqual(2560);
});

it('stores an already-small image untouched', function (): void {
    $file = copTestImage('image/jpeg', 800, 600);

    $result = app(ImageCompressionService::class)->compressUploadedFile($file);

    expect($result)->toBe($file)
        ->and(copTestImageDimension((string) $result->getRealPath()))->toBe(800);
});

it('re-encodes a file over the byte threshold without downscaling it', function (): void {
    config(['cop.image.recompress_threshold_mb' => 0]);

    $file = copTestImage('image/jpeg', 800, 600);

    $result = app(ImageCompressionService::class)->compressUploadedFile($file);

    expect($result)->not->toBe($file)
        ->and(copTestImageDimension((string) $result->getRealPath()))->toBe(800);
});

it('downscales a PNG and keeps the PNG mime', function (): void {
    $result = app(ImageCompressionService::class)->compressUploadedFile(copTestImage('image/png', 3000, 2000));

    expect($result->getMimeType())->toBe('image/png')
        ->and(copTestImageDimension((string) $result->getRealPath()))->toBeLessThanOrEqual(2560);
});

it('downscales a WebP and keeps the WebP mime', function (): void {
    $result = app(ImageCompressionService::class)->compressUploadedFile(copTestImage('image/webp', 3000, 2000));

    expect($result->getMimeType())->toBe('image/webp')
        ->and(copTestImageDimension((string) $result->getRealPath()))->toBeLessThanOrEqual(2560);
});

it('returns a GIF untouched', function (): void {
    $file = copTestImage('image/gif', 3000, 2000);

    $result = app(ImageCompressionService::class)->compressUploadedFile($file);

    expect($result)->toBe($file);
});

it('never upscales an image', function (): void {
    $file = copTestImage('image/jpeg', 800, 600);

    $result = app(ImageCompressionService::class)->compressUploadedFile($file);

    expect(copTestImageDimension((string) $result->getRealPath()))->toBe(800);
});

it('returns an unreadable file untouched without throwing', function (): void {
    config(['cop.image.recompress_threshold_mb' => 0]);

    $path = sys_get_temp_dir().'/cop_corrupt_'.bin2hex(random_bytes(8)).'.jpg';
    file_put_contents($path, 'not an image at all');
    $file = new UploadedFile($path, 'corrupt.jpg', 'image/jpeg', null, true);

    $result = app(ImageCompressionService::class)->compressUploadedFile($file);

    expect($result)->toBe($file);
});

it('bypasses processing entirely when the kill switch is on', function (): void {
    config(['cop.image.max_dimension' => 0]);

    $file = copTestImage('image/jpeg', 4000, 3000);

    $result = app(ImageCompressionService::class)->compressUploadedFile($file);

    expect($result)->toBe($file);
});

it('returns non-image files such as videos untouched without reading with GD', function (): void {
    config(['cop.image.recompress_threshold_mb' => 0]);

    $path = sys_get_temp_dir().'/test_video_'.bin2hex(random_bytes(8)).'.mp4';
    file_put_contents($path, 'fake mp4 video bytes');
    $file = new UploadedFile($path, 'sample.mp4', 'video/mp4', null, true);

    $result = app(ImageCompressionService::class)->compressUploadedFile($file);

    expect($result)->toBe($file);
});

it('compresses an oversized file on disk via compressFilePath', function (): void {
    $file = copTestImage('image/jpeg', 3200, 2400);

    $targetPath = app(ImageCompressionService::class)->compressFilePath(
        (string) $file->getRealPath(),
        'image/jpeg',
        'sample.jpg',
    );

    expect($targetPath)->not->toBe((string) $file->getRealPath())
        ->and(copTestImageDimension($targetPath))->toBeLessThanOrEqual(2560);
});
