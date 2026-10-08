<?php

use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Jobs\ProcessMediaAssetJob;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\MediaLibrary\Notifications\MediaAssetFailedNotification;
use App\Domains\MediaLibrary\Notifications\MediaAssetReadyNotification;
use App\Domains\MediaLibrary\Services\ChunkStorageService;
use App\Domains\MediaLibrary\Services\MediaAssetStorageService;
use App\Domains\MediaLibrary\Services\MediaAssetValidationService;
use App\Domains\MediaLibrary\Services\MediaQuotaService;
use App\Domains\Settings\Models\AppSetting;
use App\Shared\Exceptions\FileSizeMismatchException;
use App\Shared\Exceptions\InvalidMediaFileException;
use App\Shared\Exceptions\StorageQuotaExceededException;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Storage::fake('private');
});

/**
 * Create a processing asset owned by the fixture's field supervisor with one
 * real-image chunk already stored. Declared bytes default to the real chunk
 * size; pass an override to force a size mismatch.
 *
 * @param  array{market: mixed, project: mixed, manager: mixed, field: mixed, admin: mixed}  $fixture
 */
function processingAssetWithOneChunk(array $fixture, ?int $declaredBytes = null): MediaAsset
{
    $split = splitFileIntoChunks(UploadedFile::fake()->image('photo.jpg', 1200, 800), 1);

    $asset = MediaAsset::factory()->create([
        'user_id' => $fixture['field']->id,
        'market_id' => $fixture['market']->id,
        'project_id' => $fixture['project']->id,
        'mime_type' => 'image/jpeg',
        'file_size_bytes' => $declaredBytes ?? $split['bytes'],
        'total_chunks' => 1,
        'status' => MediaAssetStatus::PROCESSING,
    ]);

    app(ChunkStorageService::class)->storeChunk($asset->id, 0, $split['files'][0]);

    return $asset;
}

/**
 * @return array{chunks: ChunkStorageService, storage: MediaAssetStorageService, quota: MediaQuotaService, validation: MediaAssetValidationService}
 */
function jobServices(): array
{
    return [
        'chunks' => app(ChunkStorageService::class),
        'storage' => app(MediaAssetStorageService::class),
        'quota' => app(MediaQuotaService::class),
        'validation' => app(MediaAssetValidationService::class),
    ];
}

/**
 * @param  array{chunks: ChunkStorageService, storage: MediaAssetStorageService, quota: MediaQuotaService, validation: MediaAssetValidationService}  $services
 */
function runJob(ProcessMediaAssetJob $job, array $services): void
{
    $job->handle($services['chunks'], $services['storage'], $services['quota'], $services['validation']);
}

it('processes a valid image to ready, storing media and notifying the uploader', function (): void {
    Notification::fake();

    $fixture = mediaStaffFixture();
    $asset = processingAssetWithOneChunk($fixture);

    runJob(new ProcessMediaAssetJob($asset->id), jobServices());

    $asset->refresh();

    expect($asset->status)->toBe(MediaAssetStatus::READY)
        ->and($asset->getMedia(MediaAsset::COLLECTION))->toHaveCount(1)
        ->and($asset->getFirstMedia(MediaAsset::COLLECTION)?->getCustomProperty('declared_mime'))->toBe('image/jpeg');

    // Chunks consumed, used-bytes cache invalidated, uploader notified.
    expect(app(ChunkStorageService::class)->countChunks($asset->id))->toBe(0)
        ->and(Cache::has('media.quota.used:'.$fixture['field']->id))->toBeFalse();

    Notification::assertSentTo(
        $fixture['field'],
        MediaAssetReadyNotification::class,
        function (MediaAssetReadyNotification $notification) use ($fixture, $asset): bool {
            $data = $notification->toArray($fixture['field']);

            return $data['title'] === 'Photo ready'
                && $data['asset_id'] === $asset->id
                && $data['filename'] === $asset->original_filename
                && str_contains($data['body'], 'finished processing');
        }
    );
});

it('extracts EXIF GPS metadata from the assembled file', function (): void {
    $fixture = mediaStaffFixture();
    $gps = mediaJpegWithExifGps();
    $split = splitFileIntoChunks($gps, 1);

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

    runJob(new ProcessMediaAssetJob($asset->id), jobServices());

    $asset->refresh();

    expect($asset->status)->toBe(MediaAssetStatus::READY)
        ->and($asset->gps_latitude)->not->toBeNull()
        ->and($asset->gps_latitude)->toBeGreaterThan(40.0)
        ->and($asset->gps_longitude)->toBeGreaterThan(51.0)
        ->and($asset->gps_accuracy)->toBe(5.0);
});

it('marks the asset failed with the error when the assembled size mismatches', function (): void {
    $fixture = mediaStaffFixture();
    $asset = processingAssetWithOneChunk($fixture, 99_999_999_999);

    $job = new ProcessMediaAssetJob($asset->id);

    expect(fn () => runJob($job, jobServices()))
        ->toThrow(FileSizeMismatchException::class, 'Assembled file size does not match declared size.');

    $job->failed(new FileSizeMismatchException('Assembled file size does not match declared size.'));

    $asset->refresh();

    expect($asset->status)->toBe(MediaAssetStatus::FAILED)
        ->and($asset->validation_error)->toBe('Assembled file size does not match declared size.');

    // Chunks are preserved so a later retry (or manual re-run) can succeed.
    expect(app(ChunkStorageService::class)->countChunks($asset->id))->toBe(1);
});

it('rejects a file whose magic bytes are not a supported image and notifies on failure', function (): void {
    Notification::fake();

    $fixture = mediaStaffFixture();
    $asset = MediaAsset::factory()->create([
        'user_id' => $fixture['field']->id,
        'market_id' => $fixture['market']->id,
        'project_id' => $fixture['project']->id,
        'original_filename' => 'corrupt.jpg',
        'mime_type' => 'image/jpeg',
        'file_size_bytes' => 4096,
        'total_chunks' => 1,
        'status' => MediaAssetStatus::PROCESSING,
    ]);

    app(ChunkStorageService::class)->storeChunk($asset->id, 0, mediaByteFile('corrupt.jpg', 4096));

    $job = new ProcessMediaAssetJob($asset->id);

    expect(fn () => runJob($job, jobServices()))->toThrow(InvalidMediaFileException::class);

    $job->failed(new InvalidMediaFileException('This file appears corrupt or is not a supported image. Try re-uploading.'));

    $asset->refresh();

    expect($asset->status)->toBe(MediaAssetStatus::FAILED)
        ->and($asset->validation_error)->toContain('corrupt');

    Notification::assertSentTo(
        $fixture['field'],
        MediaAssetFailedNotification::class,
        function (MediaAssetFailedNotification $notification) use ($fixture, $asset): bool {
            $data = $notification->toArray($fixture['field']);

            return $data['title'] === 'Photo upload failed'
                && $data['asset_id'] === $asset->id
                && str_contains($data['body'], 'could not be processed');
        }
    );
});

it('does not resurrect an asset that permanently failed', function (): void {
    $fixture = mediaStaffFixture();
    $asset = MediaAsset::factory()->create([
        'user_id' => $fixture['field']->id,
        'market_id' => $fixture['market']->id,
        'project_id' => $fixture['project']->id,
        'file_size_bytes' => 2048,
        'total_chunks' => 1,
        'status' => MediaAssetStatus::FAILED,
        'validation_error' => 'Upload abandoned',
    ]);

    runJob(new ProcessMediaAssetJob($asset->id), jobServices());

    expect($asset->fresh()->status)->toBe(MediaAssetStatus::FAILED)
        ->and($asset->getMedia(MediaAsset::COLLECTION))->toHaveCount(0);
});

it('does not reprocess an asset that is already ready', function (): void {
    $fixture = mediaStaffFixture();
    $asset = MediaAsset::factory()->create([
        'user_id' => $fixture['field']->id,
        'market_id' => $fixture['market']->id,
        'project_id' => $fixture['project']->id,
        'file_size_bytes' => 2048,
        'total_chunks' => 1,
        'status' => MediaAssetStatus::READY,
    ]);

    runJob(new ProcessMediaAssetJob($asset->id), jobServices());

    expect($asset->fresh()->status)->toBe(MediaAssetStatus::READY)
        ->and($asset->getMedia(MediaAsset::COLLECTION))->toHaveCount(0);
});

it('fails the job when the final quota re-check exceeds the remaining limit', function (): void {
    $fixture = mediaStaffFixture();
    AppSetting::set('media.quota.supervisor_bytes', '1000');

    // Asset reassembles 500 bytes above its declared size, so the job's quota
    // re-check sees a positive delta (used 600 + delta 500 > limit 1000).
    $split = splitFileIntoChunks(UploadedFile::fake()->image('photo.jpg', 1200, 800), 1);
    $asset = MediaAsset::factory()->create([
        'user_id' => $fixture['field']->id,
        'market_id' => $fixture['market']->id,
        'project_id' => $fixture['project']->id,
        'mime_type' => 'image/jpeg',
        'file_size_bytes' => $split['bytes'] - 500,
        'total_chunks' => 1,
        'status' => MediaAssetStatus::PROCESSING,
    ]);
    app(ChunkStorageService::class)->storeChunk($asset->id, 0, $split['files'][0]);

    Cache::put('media.quota.used:'.$fixture['field']->id, 600);

    $job = new ProcessMediaAssetJob($asset->id);

    expect(fn () => runJob($job, jobServices()))
        ->toThrow(StorageQuotaExceededException::class);

    expect($asset->fresh()->status)->toBe(MediaAssetStatus::PROCESSING)
        ->and(app(ChunkStorageService::class)->countChunks($asset->id))->toBe(1);
});
