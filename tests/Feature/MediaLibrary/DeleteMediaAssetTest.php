<?php

use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
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

it('lets the owner delete their own ready asset', function (): void {
    $fixture = mediaStaffFixture();
    $asset = mediaReadyAsset($fixture);

    Cache::put('media.quota.used:'.$fixture['field']->id, 99999);

    $this->actingAs($fixture['field'])
        ->deleteJson(route('media-library.destroy', $asset))
        ->assertNoContent();

    expect($asset->fresh()->trashed())->toBeTrue()
        ->and($asset->getMedia(MediaAsset::COLLECTION))->toHaveCount(0)
        ->and(Cache::has('media.quota.used:'.$fixture['field']->id))->toBeFalse();
});

it('lets an admin delete any user\u2019s asset', function (): void {
    $fixture = mediaStaffFixture();
    $asset = mediaReadyAsset($fixture);

    $this->actingAs($fixture['admin'])
        ->deleteJson(route('media-library.destroy', $asset))
        ->assertNoContent();

    expect($asset->fresh()->trashed())->toBeTrue();
});

it('forbids an unrelated manager from deleting an asset', function (): void {
    $fixture = mediaStaffFixture();
    $asset = mediaReadyAsset($fixture);

    $outsider = User::factory()->create()->assignRole(Role::MANAGER->value);

    $this->actingAs($outsider)
        ->deleteJson(route('media-library.destroy', $asset))
        ->assertStatus(403);

    expect($asset->fresh()->trashed())->toBeFalse();
});

it('forbids a field supervisor from deleting another user’s asset', function (): void {
    $fixture = mediaStaffFixture();
    $asset = mediaReadyAsset($fixture); // uploaded by $fixture['field']

    $other = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $other->markets()->attach($fixture['market']->id);
    $fixture['project']->fieldSupervisors()->attach($other->id);

    $this->actingAs($other)
        ->deleteJson(route('media-library.destroy', $asset))
        ->assertStatus(403);

    expect($asset->fresh()->trashed())->toBeFalse();
});

it('lets a field supervisor delete their own asset', function (): void {
    $fixture = mediaStaffFixture();
    $asset = mediaReadyAsset($fixture);

    $this->actingAs($fixture['field'])
        ->deleteJson(route('media-library.destroy', $asset))
        ->assertNoContent();

    expect($asset->fresh()->trashed())->toBeTrue();
});

it('lets a manager delete a supervisor’s asset', function (): void {
    $fixture = mediaStaffFixture();
    $asset = mediaReadyAsset($fixture);

    $this->actingAs($fixture['manager'])
        ->deleteJson(route('media-library.destroy', $asset))
        ->assertNoContent();

    expect($asset->fresh()->trashed())->toBeTrue();
});

it('refuses to delete an asset while its upload is processing', function (): void {
    $fixture = mediaStaffFixture();
    $asset = MediaAsset::factory()->create([
        'user_id' => $fixture['field']->id,
        'status' => 'processing',
    ]);

    $this->actingAs($fixture['field'])
        ->deleteJson(route('media-library.destroy', $asset))
        ->assertStatus(409);

    expect($asset->fresh()->trashed())->toBeFalse();
});

it('keeps photos created from the asset intact after deletion', function (): void {
    $fixture = mediaStaffFixture();
    $asset = mediaReadyAsset($fixture);
    $selection = mediaCategorySelection($fixture);

    $this->actingAs($fixture['field'])
        ->postJson(route('media-library.assign'), [
            'media_asset_id' => $asset->id,
            'category_selection_id' => $selection->id,
        ])
        ->assertStatus(201);

    $photo = $selection->photos()->sole();

    $this->actingAs($fixture['field'])
        ->deleteJson(route('media-library.destroy', $asset))
        ->assertNoContent();

    expect($photo->fresh()->getMedia('photos'))->toHaveCount(1)
        ->and(MediaAsset::query()->find($asset->id))->toBeNull();
});
