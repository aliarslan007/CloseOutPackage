<?php

use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\MediaLibrary\Models\MediaAssetPhoto;
use App\Domains\Photos\Models\Photo;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Storage::fake('private');
});

it('assigns a ready asset into a category as a new pending photo', function (): void {
    $fixture = mediaStaffFixture();
    $asset = mediaReadyAsset($fixture);
    $asset->update([
        'gps_latitude' => 40.1234567,
        'gps_longitude' => -74.1234567,
        'gps_accuracy' => 5.0,
        'captured_at' => now()->subDay(),
    ]);
    $selection = mediaCategorySelection($fixture);

    mediaAssign($fixture, $asset->id, $selection->id)
        ->assertStatus(201)
        ->assertJsonPath('data.status', 'pending');

    $photo = Photo::query()->where('package_category_selection_id', $selection->id)->sole();

    expect($photo->uploaded_by)->toBe($fixture['field']->id)
        ->and($photo->position)->toBe(1)
        ->and($photo->gps_latitude)->toBe(40.1234567)
        ->and($photo->gps_accuracy)->toBe(5.0)
        ->and($photo->captured_at->isYesterday())->toBeTrue()
        ->and($photo->getMedia('photos'))->toHaveCount(1);

    expect(MediaAssetPhoto::query()
        ->where('media_asset_id', $asset->id)
        ->where('photo_id', $photo->id)
        ->exists())->toBeTrue();
});

it('compresses the COP copy of an oversized library asset but leaves the master untouched', function (): void {
    $fixture = mediaStaffFixture();
    $selection = mediaCategorySelection($fixture);

    // Upload an oversized (3000px) image through the real chunked pipeline.
    $oversized = copTestImage('image/jpeg', 3000, 2000);
    $asset = mediaReadyAsset($fixture, file: $oversized);

    mediaAssign($fixture, $asset->id, $selection->id)->assertStatus(201);

    $photo = Photo::query()->where('package_category_selection_id', $selection->id)->sole();
    $copied = $photo->getFirstMedia('photos');

    expect($copied)->not->toBeNull()
        // COP duplicate is compressed...
        ->and(copTestImageDimension($copied->getPath()))->toBeLessThanOrEqual(2560)
        // ...while the Media Library master keeps its full resolution.
        ->and(copTestImageDimension($asset->getFirstMedia(MediaAsset::COLLECTION)->getPath()))->toBe(3000);
});

it('rejects an asset that is not ready yet', function (): void {
    $fixture = mediaStaffFixture();
    $image = mediaByteFile('photo.jpg', 100);
    $split = splitFileIntoChunks($image, 1);

    $response = $this->actingAs($fixture['field'])
        ->postJson(route('media-library.upload.initiate'), [
            'filename' => 'photo.jpg',
            'total_size' => $split['bytes'],
            'total_chunks' => 1,
            'mime_type' => 'image/jpeg',
            'project_id' => $fixture['project']->id,
        ])
        ->assertOk();

    $selection = mediaCategorySelection($fixture);

    mediaAssign($fixture, (int) $response->json('asset_id'), $selection->id)
        ->assertStatus(422)
        ->assertJsonPath('message', 'This file has not finished processing yet. Please wait a moment and try again.');
});

it('returns 409 when the same asset is assigned into the same category twice', function (): void {
    $fixture = mediaStaffFixture();
    $asset = mediaReadyAsset($fixture);
    $selection = mediaCategorySelection($fixture, 3);

    mediaAssign($fixture, $asset->id, $selection->id)->assertStatus(201);

    mediaAssign($fixture, $asset->id, $selection->id)
        ->assertStatus(409)
        ->assertJsonPath('message', 'This file is already placed in this category.');

    expect(Photo::query()->where('package_category_selection_id', $selection->id)->count())->toBe(1);
});

it('rejects an assignment when the category bucket is full', function (): void {
    $fixture = mediaStaffFixture();
    $asset = mediaReadyAsset($fixture);
    $selection = mediaCategorySelection($fixture, 1);

    $package = $selection->load('stage.package')->stage->package;

    Photo::factory()->create([
        'closeout_package_id' => $package->id,
        'package_stage_id' => $selection->package_stage_id,
        'package_category_selection_id' => $selection->id,
        'uploaded_by' => $fixture['field']->id,
    ]);

    mediaAssign($fixture, $asset->id, $selection->id)
        ->assertStatus(422)
        ->assertJsonPath('message', 'This category allows 1 photo(s). All slots are filled.');
});

it('blocks assignment into a submitted package', function (): void {
    $fixture = mediaStaffFixture();
    $asset = mediaReadyAsset($fixture);
    $selection = mediaCategorySelection($fixture);

    $selection->load('stage.package')->stage->package->update(['status' => 'submitted']);

    mediaAssign($fixture, $asset->id, $selection->id)
        ->assertStatus(422)
        ->assertJsonPath('message', 'This package has been submitted. Only flagged photos can be replaced.');
});

it('forbids an unrelated manager from assigning an asset', function (): void {
    $fixture = mediaStaffFixture();
    $asset = mediaReadyAsset($fixture);
    $selection = mediaCategorySelection($fixture);

    $outsider = User::factory()->create()->assignRole(Role::MANAGER->value);

    $this->actingAs($outsider)
        ->postJson(route('media-library.assign'), [
            'media_asset_id' => $asset->id,
            'category_selection_id' => $selection->id,
        ])
        ->assertStatus(403);
});

it('allows an admin to assign anyone\u2019s asset', function (): void {
    $fixture = mediaStaffFixture();
    $asset = mediaReadyAsset($fixture);
    $selection = mediaCategorySelection($fixture);

    $this->actingAs($fixture['admin'])
        ->postJson(route('media-library.assign'), [
            'media_asset_id' => $asset->id,
            'category_selection_id' => $selection->id,
        ])
        ->assertStatus(201);
});

it('assigns multiple ready assets into a category in a single batch', function (): void {
    $fixture = mediaStaffFixture();
    $asset1 = mediaReadyAsset($fixture);
    $asset2 = mediaReadyAsset($fixture);
    $selection = mediaCategorySelection($fixture, 3);

    $this->actingAs($fixture['field'])
        ->postJson(route('media-library.assign'), [
            'media_asset_ids' => [$asset1->id, $asset2->id],
            'category_selection_id' => $selection->id,
        ])
        ->assertStatus(201)
        ->assertJsonPath('count', 2);

    expect(Photo::query()->where('package_category_selection_id', $selection->id)->count())->toBe(2);
});

it('returns a user-friendly error when assigning an asset to a deleted category', function (): void {
    $fixture = mediaStaffFixture();
    $asset = mediaReadyAsset($fixture);
    $selection = mediaCategorySelection($fixture);

    $selection->templatePictureCategory->delete();

    $this->actingAs($fixture['field'])
        ->postJson(route('media-library.assign'), [
            'media_asset_id' => $asset->id,
            'category_selection_id' => $selection->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'category_selection_id' => 'This photo category was removed from the template by an administrator. Please refresh your page.',
        ]);
});

it('auto-approves a photo when assigned into a category by a manager', function (): void {
    $fixture = mediaStaffFixture();
    $asset = mediaReadyAsset($fixture);
    $selection = mediaCategorySelection($fixture);

    $this->actingAs($fixture['manager'])
        ->postJson(route('media-library.assign'), [
            'media_asset_id' => $asset->id,
            'category_selection_id' => $selection->id,
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.status', 'approved');

    $photo = Photo::query()->where('package_category_selection_id', $selection->id)->sole();

    expect($photo->uploaded_by)->toBe($fixture['manager']->id)
        ->and($photo->status->value)->toBe('approved')
        ->and($photo->is_approved)->toBeTrue()
        ->and($photo->approved_by)->toBe($fixture['manager']->id)
        ->and($photo->photoApproval)->not->toBeNull()
        ->and($photo->photoApproval->decision->value)->toBe('approved');
});
