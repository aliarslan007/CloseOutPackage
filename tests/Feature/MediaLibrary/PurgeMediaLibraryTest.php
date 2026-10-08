<?php

use App\Domains\Markets\Models\Market;
use App\Domains\MediaLibrary\Actions\DeleteMediaAssetAction;
use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Jobs\PurgeMediaLibraryJob;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\MediaLibrary\Services\ChunkStorageService;
use App\Domains\MediaLibrary\Services\MediaPurgeService;
use App\Domains\MediaLibrary\Services\MediaQuotaService;
use App\Domains\Projects\Actions\DeleteProjectAction;
use App\Domains\Projects\Models\Project;
use App\Domains\ScopeOfWork\Models\SowAnnotatedImage;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Storage::fake('private');

    $this->admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $this->manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $this->supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
});

/**
 * A ready asset with a real file on the fake private disk, so the assertions
 * prove the bytes were released and not just the database row.
 */
function purgeAsset(User $owner, int $bytes = 2048, MediaAssetStatus $status = MediaAssetStatus::READY): MediaAsset
{
    $asset = MediaAsset::factory()->create([
        'user_id' => $owner->id,
        'status' => $status,
        'file_size_bytes' => $bytes,
    ]);

    $asset
        ->addMedia(UploadedFile::fake()->create("photo-{$asset->id}.jpg", $bytes, 'image/jpeg'))
        ->toMediaCollection(MediaAsset::COLLECTION);

    return $asset->fresh();
}

/** Runs the sweep to completion without needing a worker. */
function drainPurge(int $afterId = 0): void
{
    $cursor = $afterId;
    $app = app();

    // Bounded so a cursor bug fails the test instead of hanging the suite.
    for ($page = 0; $page < 50; $page++) {
        $app->make(PurgeMediaLibraryJob::class, ['afterId' => $cursor])->handle(
            $app->make(MediaPurgeService::class),
            $app->make(DeleteMediaAssetAction::class),
            $app->make(ChunkStorageService::class),
            $app->make(MediaQuotaService::class),
        );

        if (($app->make(MediaPurgeService::class)->progress()['state'] ?? null) === 'completed') {
            return;
        }

        $cursor = (int) MediaAsset::query()
            ->whereNull('deleted_at')
            ->where('id', '>', $cursor)
            ->orderBy('id')
            ->value('id');
    }

    throw new RuntimeException('Purge did not terminate within 50 pages.');
}

/** SOW has no factories, so build the minimum rows the FKs require. */
function sowReportWithCover(int $coverAssetId): SowReport
{
    $market = Market::factory()->create();

    return SowReport::create([
        'project_id' => Project::factory()->create(['market_id' => $market->id])->id,
        'market_id' => $market->id,
        'created_by' => test()->admin->id,
        'title' => 'SOW Report',
        'cover_media_asset_id' => $coverAssetId,
    ]);
}

function sowAnnotation(int $sowReportId, int $mediaAssetId): SowAnnotatedImage
{
    return SowAnnotatedImage::create([
        'sow_report_id' => $sowReportId,
        'media_asset_id' => $mediaAssetId,
        'annotation_layer' => ['shapes' => []],
    ]);
}

it('hides the purge from non admins', function (): void {
    Queue::fake();

    $this->actingAs($this->manager)->getJson(route('media-library.purge.preview'))->assertForbidden();
    $this->actingAs($this->supervisor)->postJson(route('media-library.purge.store'), [
        'confirmation' => MediaPurgeService::CONFIRMATION_PHRASE,
        'expected_total' => 0,
    ])->assertForbidden();

    Queue::assertNotPushed(PurgeMediaLibraryJob::class);
});

it('previews the real counts and protects sow referenced assets', function (): void {
    $deletable = purgeAsset($this->supervisor, 2048);
    $alsoDeletable = purgeAsset($this->supervisor, 4096);

    $annotated = purgeAsset($this->supervisor, 8192);
    $cover = purgeAsset($this->manager, 16384);

    $sowReport = sowReportWithCover($cover->id);
    sowAnnotation($sowReport->id, $annotated->id);

    $response = $this->actingAs($this->admin)->getJson(route('media-library.purge.preview'));

    $response->assertOk();

    expect($response->json('preview.total_assets'))->toBe(4)
        ->and($response->json('preview.deletable_assets'))->toBe(2)
        ->and($response->json('preview.deletable_bytes'))->toBe(2048 + 4096)
        ->and($response->json('preview.protected_assets'))->toBe(2)
        ->and($response->json('preview.protected_reasons.annotated_images'))->toBe(1)
        ->and($response->json('preview.protected_reasons.report_covers'))->toBe(1)
        ->and($response->json('preview.uploaders'))->toBe(2)
        ->and($response->json('confirmation_phrase'))->toBe(MediaPurgeService::CONFIRMATION_PHRASE);

    expect($deletable)->not->toBeNull()->and($alsoDeletable)->not->toBeNull();
});

it('refuses to start without the exact confirmation phrase', function (): void {
    Queue::fake();

    purgeAsset($this->supervisor);

    $this->actingAs($this->admin)
        ->postJson(route('media-library.purge.store'), [
            'confirmation' => 'delete all media',
            'expected_total' => 1,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('confirmation');

    Queue::assertNotPushed(PurgeMediaLibraryJob::class);
});

it('refuses to start when the library changed since the preview', function (): void {
    Queue::fake();

    purgeAsset($this->supervisor);

    // The admin reviewed a total of 1; a second upload lands before confirming.
    purgeAsset($this->supervisor);

    $this->actingAs($this->admin)
        ->postJson(route('media-library.purge.store'), [
            'confirmation' => MediaPurgeService::CONFIRMATION_PHRASE,
            'expected_total' => 1,
        ])
        ->assertStatus(409)
        ->assertJsonPath('preview.total_assets', 2);

    Queue::assertNotPushed(PurgeMediaLibraryJob::class);
});

it('refuses a second purge while one is already running', function (): void {
    Queue::fake();

    purgeAsset($this->supervisor);
    app(MediaPurgeService::class)->start(1, $this->admin);

    $this->actingAs($this->admin)
        ->postJson(route('media-library.purge.store'), [
            'confirmation' => MediaPurgeService::CONFIRMATION_PHRASE,
            'expected_total' => 1,
        ])
        ->assertStatus(409);

    Queue::assertNotPushed(PurgeMediaLibraryJob::class);
});

it('protects a canonical asset whose bytes a live duplicate pointer resolves through', function (): void {
    $canonical = purgeAsset($this->supervisor, 8192);
    $canonicalMediaId = $canonical->getFirstMedia(MediaAsset::COLLECTION)?->id;

    // A pointer with nothing referencing it is purgeable in its own right;
    // what must survive is the canonical whose bytes it resolves through.
    $pointer = MediaAsset::factory()->create([
        'user_id' => $this->supervisor->id,
        'status' => MediaAssetStatus::READY,
        'duplicate_of_asset_id' => $canonical->id,
    ]);

    app(MediaPurgeService::class)->start(2, $this->admin);
    drainPurge();

    expect(MediaAsset::query()->find($canonical->id)?->trashed())->toBeFalse()
        ->and(Media::query()->find($canonicalMediaId))->not->toBeNull()
        ->and($pointer->fresh()->getFirstMedia(MediaAsset::COLLECTION)?->id)->toBe($canonicalMediaId);
});

it('deletes library assets and their files while protecting sow references', function (): void {
    $deletable = purgeAsset($this->supervisor, 2048);
    $annotated = purgeAsset($this->supervisor, 8192);
    $cover = purgeAsset($this->manager, 16384);

    $sowReport = sowReportWithCover($cover->id);
    $annotation = sowAnnotation($sowReport->id, $annotated->id);

    $deletableMediaId = $deletable->getFirstMedia(MediaAsset::COLLECTION)?->id;
    expect($deletableMediaId)->not->toBeNull();

    app(MediaPurgeService::class)->start(3, $this->admin);
    drainPurge();

    // Row is soft-deleted, spatie media row is gone, file is gone.
    expect(MediaAsset::withTrashed()->find($deletable->id))->not->toBeNull()
        ->and(MediaAsset::withTrashed()->find($deletable->id)->trashed())->toBeTrue()
        ->and(Media::query()->find($deletableMediaId))->toBeNull();

    // SOW-protected assets and their annotation rows survive untouched.
    expect(MediaAsset::query()->find($annotated->id)?->trashed())->toBeFalse()
        ->and(MediaAsset::query()->find($cover->id)?->trashed())->toBeFalse()
        ->and(SowAnnotatedImage::query()->find($annotation->id))->not->toBeNull();

    $progress = app(MediaPurgeService::class)->progress();

    expect($progress['state'])->toBe('completed')
        ->and($progress['deleted'])->toBe(1)
        ->and($progress['deleted_bytes'])->toBe(2048)
        ->and($progress['protected_skipped'])->toBe(2)
        ->and($progress['failed'])->toBe(0)
        ->and($progress['requested_by']['id'])->toBe($this->admin->id);
});

it('skips uploads still in flight instead of cancelling them', function (): void {
    $processing = purgeAsset($this->supervisor, 2048, MediaAssetStatus::PROCESSING);
    $ready = purgeAsset($this->supervisor, 4096);

    app(MediaPurgeService::class)->start(2, $this->admin);
    drainPurge();

    $progress = app(MediaPurgeService::class)->progress();

    expect($progress['in_progress_skipped'])->toBe(1)
        ->and($progress['deleted'])->toBe(1)
        ->and(MediaAsset::withTrashed()->find($processing->id)?->trashed())->toBeFalse()
        ->and(MediaAsset::withTrashed()->find($ready->id)?->trashed())->toBeTrue();
});

it('reclaims the preserved original in the source collection', function (): void {
    $asset = purgeAsset($this->supervisor);

    $asset
        ->addMedia(UploadedFile::fake()->create('original.jpg', 1024, 'image/jpeg'))
        ->toMediaCollection(MediaAsset::SOURCE_COLLECTION);

    $sourceMediaId = $asset->getFirstMedia(MediaAsset::SOURCE_COLLECTION)?->id;
    expect($sourceMediaId)->not->toBeNull();

    app(MediaPurgeService::class)->start(1, $this->admin);
    drainPurge();

    expect(Media::query()->find($sourceMediaId))->toBeNull();
});

it('returns every storage stat to zero after the purge', function (): void {
    // The trap this feature exists to avoid: the header reads used bytes from
    // MediaAsset rows, so deleting files without soft-deleting the rows would
    // leave the stats reporting bytes that no longer exist.
    purgeAsset($this->supervisor, 2048);
    purgeAsset($this->supervisor, 4096);
    purgeAsset($this->manager, 8192);

    $quota = app(MediaQuotaService::class);

    expect($quota->usedBytes($this->supervisor))->toBe(6144)
        ->and($quota->systemUsage()['total_bytes'])->toBeGreaterThan(0);

    app(MediaPurgeService::class)->start(3, $this->admin);
    drainPurge();

    expect($quota->usedBytes($this->supervisor))->toBe(0)
        ->and($quota->systemUsage()['total_bytes'])->toBe(0);

    $stats = $this->actingAs($this->admin)->getJson(route('media-library.stats'))->assertOk();

    expect($stats->json('counts.total'))->toBe(0)
        ->and($stats->json('quota.used_bytes'))->toBe(0)
        ->and($stats->json('system.total_bytes'))->toBe(0);
});

it('reports a purge already in progress to the polling dialog', function (): void {
    app(MediaPurgeService::class)->start(42, $this->admin);

    $this->actingAs($this->admin)
        ->getJson(route('media-library.purge.status'))
        ->assertOk()
        ->assertJsonPath('progress.state', 'running')
        ->assertJsonPath('progress.expected_total', 42);
});

it('leaves a project deletion cascade working after the source collection fix', function (): void {
    $project = Project::factory()->create(['manager_id' => $this->manager->id]);
    $asset = MediaAsset::factory()->create(['project_id' => $project->id, 'status' => MediaAssetStatus::READY]);

    $asset
        ->addMedia(UploadedFile::fake()->create('cascade.jpg', 512, 'image/jpeg'))
        ->toMediaCollection(MediaAsset::COLLECTION);

    $mediaId = $asset->getFirstMedia(MediaAsset::COLLECTION)?->id;

    app(DeleteProjectAction::class)->execute($project, $this->admin);

    expect(Media::query()->find($mediaId))->toBeNull();
});
