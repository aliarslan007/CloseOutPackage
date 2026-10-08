<?php

declare(strict_types=1);

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\PdfGeneration\Services\PdfPhotoPageRenderer;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

it('correctly calculates photo page count by sub-category', function (): void {
    $renderer = new PdfPhotoPageRenderer;

    // 5 photos in "Final Site Overview" -> 4 on page 1, 1 on page 2 (2 pages)
    // 2 photos in "Completed Equipment" -> starts on new page -> 1 page
    // Total should be 3 photo pages
    $photoItems = [
        ['path' => '/tmp/site-1.jpg', 'heading' => 'Final Site Overview', 'category' => 'Final Site Overview', 'stageHeading' => 'Pre-Construction Pictures', 'categoryId' => 1],
        ['path' => '/tmp/site-2.jpg', 'heading' => 'Final Site Overview', 'category' => 'Final Site Overview', 'stageHeading' => 'Pre-Construction Pictures', 'categoryId' => 1],
        ['path' => '/tmp/site-3.jpg', 'heading' => 'Final Site Overview', 'category' => 'Final Site Overview', 'stageHeading' => 'Pre-Construction Pictures', 'categoryId' => 1],
        ['path' => '/tmp/site-4.jpg', 'heading' => 'Final Site Overview', 'category' => 'Final Site Overview', 'stageHeading' => 'Pre-Construction Pictures', 'categoryId' => 1],
        ['path' => '/tmp/site-5.jpg', 'heading' => 'Final Site Overview', 'category' => 'Final Site Overview', 'stageHeading' => 'Pre-Construction Pictures', 'categoryId' => 1],
        ['path' => '/tmp/eq-1.jpg', 'heading' => 'Completed Equipment', 'category' => 'Completed Equipment', 'stageHeading' => 'Pre-Construction Pictures', 'categoryId' => 2],
        ['path' => '/tmp/eq-2.jpg', 'heading' => 'Completed Equipment', 'category' => 'Completed Equipment', 'stageHeading' => 'Pre-Construction Pictures', 'categoryId' => 2],
    ];

    expect($renderer->pageCount($photoItems))->toBe(3);
});

it('groups photos by stage and sub-category preserving headings', function (): void {
    $renderer = new PdfPhotoPageRenderer;

    $photoItems = [
        ['path' => '/tmp/1.jpg', 'heading' => 'Site Overview Custom', 'category' => 'Site Overview', 'stageHeading' => 'Pre-Construction Pictures', 'categoryId' => 10],
        ['path' => '/tmp/2.jpg', 'heading' => 'Completed Equipment', 'category' => 'Completed Equipment', 'stageHeading' => 'Construction / Installation Pics', 'categoryId' => 20],
    ];

    $grouped = $renderer->groupPhotos($photoItems);

    expect($grouped)->toHaveKey('Pre-Construction Pictures')
        ->and($grouped)->toHaveKey('Construction / Installation Pics')
        ->and($grouped['Pre-Construction Pictures'])->toHaveKey('10')
        ->and($grouped['Construction / Installation Pics'])->toHaveKey('20')
        ->and($grouped['Pre-Construction Pictures']['10']['subHeading'])->toBe('Site Overview Custom');
});

it('renders pages starting each new sub-category on a new page', function (): void {
    $renderer = new PdfPhotoPageRenderer;
    $package = new CloseoutPackage;

    $photoItems = [
        ['path' => '/tmp/site-1.jpg', 'heading' => 'Final Site Overview', 'category' => 'Final Site Overview', 'stageHeading' => 'Pre-Construction Pictures', 'categoryId' => 1],
        ['path' => '/tmp/site-2.jpg', 'heading' => 'Final Site Overview', 'category' => 'Final Site Overview', 'stageHeading' => 'Pre-Construction Pictures', 'categoryId' => 1],
        ['path' => '/tmp/site-3.jpg', 'heading' => 'Final Site Overview', 'category' => 'Final Site Overview', 'stageHeading' => 'Pre-Construction Pictures', 'categoryId' => 1],
        ['path' => '/tmp/site-4.jpg', 'heading' => 'Final Site Overview', 'category' => 'Final Site Overview', 'stageHeading' => 'Pre-Construction Pictures', 'categoryId' => 1],
        ['path' => '/tmp/site-5.jpg', 'heading' => 'Final Site Overview', 'category' => 'Final Site Overview', 'stageHeading' => 'Pre-Construction Pictures', 'categoryId' => 1],
        ['path' => '/tmp/eq-1.jpg', 'heading' => 'Completed Equipment', 'category' => 'Completed Equipment', 'stageHeading' => 'Pre-Construction Pictures', 'categoryId' => 2],
    ];

    $html = $renderer->render(
        $package,
        $photoItems,
        3,
        'R1.0',
        CarbonImmutable::now(),
        null,
        null,
        1
    );

    // Should have 3 pages: 2 for Final Site Overview, 1 for Completed Equipment
    expect(substr_count($html, 'class="page"'))->toBe(3)
        ->and(substr_count($html, 'class="photo-sub-heading"'))->toBe(2)
        ->and(substr_count($html, 'class="photo-stage-heading"'))->toBe(2)
        ->and($html)->toContain('Final Site Overview')
        ->and($html)->toContain('Completed Equipment');
});
