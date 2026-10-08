<?php

use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Jobs\ProcessMediaAssetJob;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\MediaLibrary\Services\ChunkStorageService;
use App\Domains\MediaLibrary\Services\MediaAssetStorageService;
use App\Domains\MediaLibrary\Services\MediaAssetValidationService;
use App\Domains\MediaLibrary\Services\MediaQuotaService;
use App\Domains\ScopeOfWork\Actions\ImportMediaAssetsToSowAction;
use App\Domains\ScopeOfWork\DTOs\ImportMediaAssetsDTO;
use App\Domains\ScopeOfWork\Enums\SowStatus;
use App\Domains\ScopeOfWork\Models\SowAnnotatedImage;
use App\Domains\ScopeOfWork\Models\SowReport;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Storage::fake('private');
});

/**
 * Run the real upload pipeline for one file and return the resulting asset.
 * Bytes are identical across calls when $path is reused, which is exactly
 * what the duplicate guard keys on.
 */
function processDeviceUpload(array $fixture, string $path): MediaAsset
{
    $split = splitFileIntoChunks(new UploadedFile($path, basename($path), 'image/jpeg', null, true), 1);

    $asset = MediaAsset::factory()->create([
        'user_id' => $fixture['field']->id,
        'market_id' => $fixture['market']->id,
        'project_id' => $fixture['project']->id,
        'mime_type' => 'image/jpeg',
        'file_size_bytes' => $split['bytes'],
        'total_chunks' => 1,
        'status' => MediaAssetStatus::PROCESSING,
    ]);

    app(ChunkStorageService::class)->storeChunk($asset->id, 0, $split['files'][0]);

    (new ProcessMediaAssetJob($asset->id))->handle(
        app(ChunkStorageService::class),
        app(MediaAssetStorageService::class),
        app(MediaQuotaService::class),
        app(MediaAssetValidationService::class),
    );

    return $asset->refresh();
}

function deviceUploadJpegPath(): string
{
    $fake = UploadedFile::fake()->image('site.jpg');
    $path = tempnam(sys_get_temp_dir(), 'sowdev_').'.site.jpg';
    copy($fake->getRealPath(), $path);

    return $path;
}

/**
 * @param  array{market: mixed, project: mixed, manager: mixed, field: mixed, admin: mixed}  $fixture
 */
function draftSowReport(array $fixture): SowReport
{
    return SowReport::create([
        'market_id' => $fixture['market']->id,
        'created_by' => $fixture['field']->id,
        'project_id' => $fixture['project']->id,
        'status' => SowStatus::DRAFT,
        'title' => 'Device Upload SOW',
        'site_name' => 'Device Upload Site',
        'revision' => 1,
        'revision_date' => now()->toDateString(),
    ]);
}

it('stores a SOW device upload in the media library, ready and project-linked', function (): void {
    $fixture = mediaStaffFixture();

    $asset = processDeviceUpload($fixture, deviceUploadJpegPath());

    expect($asset->status)->toBe(MediaAssetStatus::READY)
        ->and($asset->duplicate_of_asset_id)->toBeNull()
        ->and($asset->project_id)->toBe($fixture['project']->id)
        ->and($asset->getFirstMedia(MediaAsset::COLLECTION))->not->toBeNull();
});

it('re-uploading the same photo to a SOW succeeds and reuses the stored copy', function (): void {
    $fixture = mediaStaffFixture();
    $path = deviceUploadJpegPath();

    $first = processDeviceUpload($fixture, $path);
    $second = processDeviceUpload($fixture, $path);

    // The second upload must not be rejected — that was the bug.
    expect($second->status)->toBe(MediaAssetStatus::READY)
        ->and($second->validation_error)->toBeNull()
        ->and($second->duplicate_of_asset_id)->toBe($first->id);

    // One physical copy, two rows: the pointer stores no file of its own.
    expect($second->getMedia(MediaAsset::COLLECTION))->toHaveCount(0)
        ->and($second->getFirstMedia(MediaAsset::COLLECTION))->not->toBeNull()
        ->and($second->getFirstMedia(MediaAsset::COLLECTION)?->id)->toBe(
            $first->getFirstMedia(MediaAsset::COLLECTION)?->id
        );
});

it('attaches the canonical asset to the SOW when a duplicate was uploaded', function (): void {
    $fixture = mediaStaffFixture();
    $path = deviceUploadJpegPath();

    $first = processDeviceUpload($fixture, $path);
    $pointer = processDeviceUpload($fixture, $path);

    $report = draftSowReport($fixture);

    $imported = app(ImportMediaAssetsToSowAction::class)->execute($report, new ImportMediaAssetsDTO(
        sowReportId: $report->id,
        mediaAssetIds: [$pointer->id],
    ));

    expect($imported)->toBe(1);

    $image = SowAnnotatedImage::query()->where('sow_report_id', $report->id)->sole();

    // The report references the asset that holds the bytes, not the pointer.
    expect($image->media_asset_id)->toBe($first->id)
        ->and($image->mediaAsset->getFirstMedia(MediaAsset::COLLECTION))->not->toBeNull();
});

it('does not add the same image to one SOW twice via a duplicate upload', function (): void {
    $fixture = mediaStaffFixture();
    $path = deviceUploadJpegPath();

    $first = processDeviceUpload($fixture, $path);
    $pointer = processDeviceUpload($fixture, $path);

    $report = draftSowReport($fixture);
    $action = app(ImportMediaAssetsToSowAction::class);

    $action->execute($report, new ImportMediaAssetsDTO(
        sowReportId: $report->id,
        mediaAssetIds: [$first->id],
    ));

    $secondImport = $action->execute($report, new ImportMediaAssetsDTO(
        sowReportId: $report->id,
        mediaAssetIds: [$pointer->id],
    ));

    expect($secondImport)->toBe(0)
        ->and(SowAnnotatedImage::query()->where('sow_report_id', $report->id)->count())->toBe(1);
});
