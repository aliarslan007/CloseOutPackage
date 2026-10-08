<?php

use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Models\MediaAsset;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Storage::fake('private');
});

it('marks abandoned processing uploads as failed and invalidates their cache', function (): void {
    $fixture = mediaStaffFixture();

    $asset = MediaAsset::factory()->create([
        'user_id' => $fixture['field']->id,
        'status' => MediaAssetStatus::PROCESSING->value,
        'created_at' => now()->subHours(3),
    ]);

    Cache::put('media.quota.used:'.$fixture['field']->id, 99999);

    $this->artisan('cop:cleanup-orphaned-media-assets')->assertSuccessful();

    expect($asset->fresh()->status)->toBe(MediaAssetStatus::FAILED)
        ->and($asset->fresh()->validation_error)->toBe('Upload abandoned')
        ->and(Cache::has('media.quota.used:'.$fixture['field']->id))->toBeFalse();
});

it('leaves recent processing uploads alone', function (): void {
    $fixture = mediaStaffFixture();

    $asset = MediaAsset::factory()->create([
        'user_id' => $fixture['field']->id,
        'status' => MediaAssetStatus::PROCESSING->value,
        'created_at' => now()->subMinutes(5),
    ]);

    $this->artisan('cop:cleanup-orphaned-media-assets')->assertSuccessful();

    expect($asset->fresh()->status)->toBe(MediaAssetStatus::PROCESSING);
});

it('deletes chunk folders whose asset row no longer exists', function (): void {
    $fixture = mediaStaffFixture();

    $live = MediaAsset::factory()->create([
        'user_id' => $fixture['field']->id,
        'status' => MediaAssetStatus::PROCESSING->value,
    ]);

    Storage::disk('private')->put("media-library/chunks/{$live->id}/000001", 'a');
    Storage::disk('private')->put('media-library/chunks/424242/000001', 'orphan');

    $this->artisan('cop:cleanup-orphaned-media-assets')->assertSuccessful();

    Storage::disk('private')->assertExists("media-library/chunks/{$live->id}/000001");
    Storage::disk('private')->assertMissing('media-library/chunks/424242');
});

it('cleans recent processing uploads when --all is provided', function (): void {
    $fixture = mediaStaffFixture();

    $asset = MediaAsset::factory()->create([
        'user_id' => $fixture['field']->id,
        'status' => MediaAssetStatus::PROCESSING->value,
        'created_at' => now()->subMinutes(2),
    ]);

    $this->artisan('cop:cleanup-orphaned-media-assets', ['--all' => true])->assertSuccessful();

    expect($asset->fresh()->status)->toBe(MediaAssetStatus::FAILED)
        ->and($asset->fresh()->validation_error)->toBe('Upload abandoned');
});

it('purges abandoned uploads completely when --delete is provided', function (): void {
    $fixture = mediaStaffFixture();

    $asset = MediaAsset::factory()->create([
        'user_id' => $fixture['field']->id,
        'status' => MediaAssetStatus::PROCESSING->value,
        'created_at' => now()->subHours(3),
    ]);

    Storage::disk('private')->put("media-library/chunks/{$asset->id}/000001", 'chunk');

    $this->artisan('cop:cleanup-orphaned-media-assets', ['--delete' => true])->assertSuccessful();

    expect(MediaAsset::find($asset->id))->toBeNull();
    Storage::disk('private')->assertMissing("media-library/chunks/{$asset->id}");
});
