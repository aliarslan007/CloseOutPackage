<?php

use App\Domains\PdfGeneration\Services\PdfPhotoItemResolver;
use App\Domains\Photos\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

it('caps photo dimensions and compresses them for the PDF', function (): void {
    Storage::fake('private');

    $photo = Photo::factory()->create();
    $photo->addMedia(UploadedFile::fake()->image('photo.jpg', 2400, 1600))
        ->toMediaCollection(Photo::COLLECTION);

    $items = app(PdfPhotoItemResolver::class)->resolve(collect([$photo]));

    expect($items)->toHaveCount(1);

    $optimized = ImageManager::gd()->read($items[0]['path']);

    expect($optimized->width())->toBeLessThanOrEqual(1200)
        ->and($optimized->height())->toBeLessThanOrEqual(1200)
        ->and($items[0]['heading'])->not->toBe('');

    @unlink($items[0]['path']);
});

it('prefers the upload-time pdf conversion and falls back to the original', function (): void {
    Storage::fake('private');

    $photo = Photo::factory()->create();
    $photo->addMedia(UploadedFile::fake()->image('photo.jpg', 2400, 1600))
        ->toMediaCollection(Photo::COLLECTION);

    $media = $photo->getFirstMedia(Photo::COLLECTION);
    expect($media)->not->toBeNull();

    // Simulate an upload-time generated conversion by copying the original
    // into the conversion's expected location and flagging it generated.
    $pdfPath = $media->getPath('pdf');
    @mkdir(dirname($pdfPath), 0777, true);
    copy($media->getPath(), $pdfPath);
    $media->markAsConversionGenerated('pdf', true);
    $media->save();

    $items = app(PdfPhotoItemResolver::class)->resolve(collect([$photo]));

    expect($items)->toHaveCount(1)
        ->and($items[0]['sourcePath'])->toBe($pdfPath)
        ->and($items[0]['path'])->not->toBeNull()
        ->and(is_file($items[0]['path']))->toBeTrue();

    @unlink($items[0]['path']);
});
