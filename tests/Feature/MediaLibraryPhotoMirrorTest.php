<?php

use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\MediaLibrary\Models\MediaAssetPhoto;
use App\Domains\Photos\Models\Photo;
use App\Domains\Templates\Enums\StageType;
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
 * A real JPEG on disk whose bytes can be wrapped into repeated
 * test-mode UploadedFile instances (dedupe needs identical content).
 */
function mirrorJpegPath(string $name = 'site.jpg'): string
{
    $fake = UploadedFile::fake()->image($name);
    $path = tempnam(sys_get_temp_dir(), 'mirror_').'.'.$name;
    copy($fake->getRealPath(), $path);

    return $path;
}

function mirrorUpload(string $path): UploadedFile
{
    return new UploadedFile($path, basename($path), 'image/jpeg', null, true);
}

function mirrorPackageStage(array $fixture): array
{
    /** @var PackageCategorySelection $selection */
    $selection = mediaCategorySelection($fixture);
    $stage = $selection->packageStage;

    return [$stage->package, $stage, $selection];
}

it('mirrors a device photo upload into the media library, linked to the project', function (): void {
    $fixture = mediaStaffFixture();
    [$package, $stage, $selection] = mirrorPackageStage($fixture);

    $this->actingAs($fixture['field'])
        ->post(route('photos.store', [$package, StageType::CONSTRUCTION->value]), [
            'photo' => mirrorUpload(mirrorJpegPath()),
            'category_selection_id' => $selection->id,
            'position' => 1,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $photo = Photo::sole();
    $asset = MediaAsset::sole();

    expect($asset->status)->toBe(MediaAssetStatus::READY)
        ->and($asset->project_id)->toBe($fixture['project']->id)
        ->and($asset->market_id)->toBe($fixture['market']->id)
        ->and($asset->user_id)->toBe($fixture['field']->id)
        ->and($asset->file_hash)->not->toBeNull()
        ->and(MediaAssetPhoto::query()
            ->where('media_asset_id', $asset->id)
            ->where('photo_id', $photo->id)
            ->exists())->toBeTrue()
        ->and($asset->getFirstMedia(MediaAsset::COLLECTION)?->getPath())->not->toBeNull();
});

it('reuses the existing library asset instead of storing a duplicate image', function (): void {
    $fixture = mediaStaffFixture();
    [$package, $stage, $selection] = mirrorPackageStage($fixture);
    $selection->update(['picture_count' => 2]);

    $path = mirrorJpegPath();
    $copy = tempnam(sys_get_temp_dir(), 'mirror_').'.jpg';
    copy($path, $copy);

    foreach ([$path, $copy] as $index => $file) {
        $this->actingAs($fixture['field'])
            ->post(route('photos.store', [$package, StageType::CONSTRUCTION->value]), [
                'photo' => mirrorUpload($file),
                'category_selection_id' => $selection->id,
                'position' => $index + 1,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    expect(Photo::query()->where('package_category_selection_id', $selection->id)->count())->toBe(2)
        ->and(MediaAsset::query()->count())->toBe(1)
        ->and(MediaAssetPhoto::query()->count())->toBe(2);
});

it('mirrors a replaced photo and dedupes it against the original bytes', function (): void {
    $fixture = mediaStaffFixture();
    [$package, $stage, $selection] = mirrorPackageStage($fixture);

    $path = mirrorJpegPath();
    $copy = tempnam(sys_get_temp_dir(), 'mirror_').'.jpg';
    copy($path, $copy);

    $this->actingAs($fixture['field'])
        ->post(route('photos.store', [$package, StageType::CONSTRUCTION->value]), [
            'photo' => mirrorUpload($path),
            'category_selection_id' => $selection->id,
            'position' => 1,
        ])
        ->assertRedirect();

    $oldPhoto = Photo::sole();

    // Flag the photo so a replacement is allowed, then replace with the same bytes.
    $oldPhoto->update(['status' => 'flagged', 'flagged_at' => now(), 'flagged_by' => $fixture['manager']->id]);

    $this->actingAs($fixture['field'])
        ->post(route('photos.replace', [$package, $oldPhoto]), [
            'photo' => mirrorUpload($copy),
            'photo_id' => (string) $oldPhoto->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    // Same bytes on replacement -> one library asset, but a pivot per photo.
    expect(MediaAsset::query()->count())->toBe(1)
        ->and(MediaAssetPhoto::query()->count())->toBe(2);
});

it('backfill: phase 1 hashes a ready asset that predates the column', function (): void {
    $fixture = mediaStaffFixture();
    $asset = mediaReadyAsset($fixture);

    $asset->update(['file_hash' => null]);

    $this->artisan('media:backfill-library')->assertSuccessful();

    expect($asset->refresh()->file_hash)->not->toBeNull();
});

it('backfill: phase 2 mirrors a legacy photo with no pivot and stays idempotent', function (): void {
    $fixture = mediaStaffFixture();
    [$package, $stage, $selection] = mirrorPackageStage($fixture);

    $this->actingAs($fixture['field'])
        ->post(route('photos.store', [$package, StageType::CONSTRUCTION->value]), [
            'photo' => mirrorUpload(mirrorJpegPath()),
            'category_selection_id' => $selection->id,
            'position' => 1,
        ])
        ->assertRedirect();

    $photo = Photo::sole();

    // Legacy state: mirrored asset + pivot removed (e.g. uploaded before the feature).
    MediaAssetPhoto::query()->where('photo_id', $photo->id)->delete();
    MediaAsset::query()->forceDelete();

    $this->artisan('media:backfill-library')->assertSuccessful();

    $asset = MediaAsset::sole();
    expect($asset->project_id)->toBe($fixture['project']->id)
        ->and(MediaAssetPhoto::query()->where('photo_id', $photo->id)->where('media_asset_id', $asset->id)->exists())->toBeTrue();

    // Second run must not create a second asset for the same photo.
    $this->artisan('media:backfill-library')->assertSuccessful();

    expect(MediaAsset::query()->count())->toBe(1)
        ->and(MediaAssetPhoto::query()->count())->toBe(1);
});

it('does not create a second asset when an existing library asset is assigned', function (): void {
    $fixture = mediaStaffFixture();
    $asset = mediaReadyAsset($fixture);
    $selection = mediaCategorySelection($fixture);

    expect($asset->file_hash)->not->toBeNull(); // hashed at finalize

    mediaAssign($fixture, $asset->id, $selection->id)->assertStatus(201);

    expect(MediaAsset::query()->count())->toBe(1);
});

it('reuses the stored copy when a direct library upload duplicates an existing image', function (): void {
    $fixture = mediaStaffFixture();
    $path = mirrorJpegPath();

    $first = mediaReadyAsset($fixture, 2, mirrorUpload($path));
    $second = mediaReadyAsset($fixture, 2, mirrorUpload($path));

    // The re-upload succeeds — it is a pointer at the asset holding the bytes,
    // not a rejection and not a second stored copy.
    expect($first->status)->toBe(MediaAssetStatus::READY)
        ->and($second->refresh()->status)->toBe(MediaAssetStatus::READY)
        ->and($second->validation_error)->toBeNull()
        ->and($second->duplicate_of_asset_id)->toBe($first->id)
        ->and($second->getMedia(MediaAsset::COLLECTION))->toHaveCount(0)
        ->and($second->getFirstMedia(MediaAsset::COLLECTION)?->id)
        ->toBe($first->getFirstMedia(MediaAsset::COLLECTION)?->id);
});
