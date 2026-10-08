<?php

use App\Domains\Markets\Models\Market;
use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
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

it('annotates a media asset and saves as a new copy preserving metadata', function (): void {
    $fixture = mediaStaffFixture();
    $asset = mediaReadyAsset($fixture);
    $asset->update([
        'gps_latitude' => 37.7749,
        'gps_longitude' => -122.4194,
        'gps_accuracy' => 5.0,
    ]);

    $fakeImage = UploadedFile::fake()->image('annotated_output.jpg', 1200, 900);

    $response = $this->actingAs($fixture['field'])
        ->postJson(route('media-library.annotate', $asset), [
            'image' => $fakeImage,
            'save_mode' => 'copy',
        ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.project_id', $fixture['project']->id)
        ->assertJsonPath('data.market_id', $fixture['market']->id)
        ->assertJsonPath('data.status', MediaAssetStatus::READY->value);

    $newAssetId = $response->json('data.id');
    expect($newAssetId)->not->toBe($asset->id);

    $newAsset = MediaAsset::find($newAssetId);
    expect($newAsset)->not->toBeNull()
        ->and($newAsset->original_filename)->toContain('_annotated')
        ->and((float) $newAsset->gps_latitude)->toEqual(37.7749)
        ->and((float) $newAsset->gps_longitude)->toEqual(-122.4194)
        ->and($newAsset->status)->toBe(MediaAssetStatus::READY);

    // Verify original asset is still intact
    expect($asset->fresh())->not->toBeNull();
});

it('annotates a media asset and overwrites existing file', function (): void {
    $fixture = mediaStaffFixture();
    $asset = mediaReadyAsset($fixture);

    $fakeImage = UploadedFile::fake()->image('replaced_output.jpg', 1200, 900);

    $response = $this->actingAs($fixture['field'])
        ->postJson(route('media-library.annotate', $asset), [
            'image' => $fakeImage,
            'save_mode' => 'overwrite',
        ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $asset->id)
        ->assertJsonPath('data.status', MediaAssetStatus::READY->value);

    expect(MediaAsset::count())->toBe(1);
});

it('forbids unauthorized users from annotating an asset', function (): void {
    $fixture = mediaStaffFixture();
    $asset = mediaReadyAsset($fixture);

    $otherMarket = Market::factory()->create(['is_active' => true]);
    $outsider = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $outsider->markets()->attach($otherMarket->id);

    $fakeImage = UploadedFile::fake()->image('annotated.jpg', 1200, 900);

    $this->actingAs($outsider)
        ->postJson(route('media-library.annotate', $asset), [
            'image' => $fakeImage,
            'save_mode' => 'copy',
        ])
        ->assertForbidden();
});

it('validates required inputs for annotate', function (): void {
    $fixture = mediaStaffFixture();
    $asset = mediaReadyAsset($fixture);

    $this->actingAs($fixture['field'])
        ->postJson(route('media-library.annotate', $asset), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['image', 'save_mode']);
});

it('persists annotation layer and source_asset_id for re-editable copies', function (): void {
    $fixture = mediaStaffFixture();
    $rootAsset = mediaReadyAsset($fixture);

    $fakeImage = UploadedFile::fake()->image('annotated_copy.jpg', 1200, 900);
    $layer = [
        'base_width' => 1200,
        'nodes' => [
            [
                'id' => 'node-1',
                'type' => 'box',
                'norm_x' => 0.2,
                'norm_y' => 0.3,
                'norm_width' => 0.4,
                'norm_height' => 0.25,
                'stroke_color' => '#EF4444',
            ],
            [
                'id' => 'node-2',
                'type' => 'arrow',
                'norm_points' => [0.1, 0.1, 0.5, 0.5],
                'stroke_color' => '#3B82F6',
            ],
        ],
    ];

    // Save initial copy with layer
    $response = $this->actingAs($fixture['field'])
        ->postJson(route('media-library.annotate', $rootAsset), [
            'image' => $fakeImage,
            'save_mode' => 'copy',
            'layer' => $layer,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.source_asset_id', $rootAsset->id)
        ->assertJsonPath('data.annotation_layer.base_width', 1200)
        ->assertJsonCount(2, 'data.annotation_layer.nodes');

    $firstCopyId = $response->json('data.id');
    $firstCopy = MediaAsset::findOrFail($firstCopyId);

    expect($firstCopy->source_asset_id)->toBe($rootAsset->id)
        ->and($firstCopy->annotation_layer['nodes'])->toHaveCount(2)
        ->and($firstCopy->source_image_url)->toBe($rootAsset->url);

    // Now re-edit the copy and save as another copy — it should inherit the original root source_asset_id
    $updatedLayer = [
        'base_width' => 1200,
        'nodes' => [
            [
                'id' => 'node-1',
                'type' => 'box',
                'norm_x' => 0.3,
                'norm_y' => 0.4,
                'norm_width' => 0.5,
                'norm_height' => 0.3,
                'stroke_color' => '#10B981',
            ],
        ],
    ];

    $fakeImage2 = UploadedFile::fake()->image('annotated_second_copy.jpg', 1200, 900);

    $response2 = $this->actingAs($fixture['field'])
        ->postJson(route('media-library.annotate', $firstCopy), [
            'image' => $fakeImage2,
            'save_mode' => 'copy',
            'layer' => $updatedLayer,
        ]);

    $response2->assertOk()
        ->assertJsonPath('data.source_asset_id', $rootAsset->id) // Preserves root ancestor
        ->assertJsonCount(1, 'data.annotation_layer.nodes');

    $secondCopy = MediaAsset::findOrFail($response2->json('data.id'));
    expect($secondCopy->source_asset_id)->toBe($rootAsset->id)
        ->and($secondCopy->source_image_url)->toBe($rootAsset->url);
});

it('preserves clean base image when overwriting an unannotated original asset', function (): void {
    $fixture = mediaStaffFixture();
    $originalAsset = mediaReadyAsset($fixture);

    expect($originalAsset->hasMedia(MediaAsset::SOURCE_COLLECTION))->toBeFalse();

    $fakeImage = UploadedFile::fake()->image('annotated_overwrite.jpg', 1200, 900);
    $layer = [
        'base_width' => 1200,
        'nodes' => [
            ['id' => 'arrow-1', 'type' => 'arrow', 'norm_points' => [0.2, 0.2, 0.8, 0.8]],
        ],
    ];

    $response = $this->actingAs($fixture['field'])
        ->postJson(route('media-library.annotate', $originalAsset), [
            'image' => $fakeImage,
            'save_mode' => 'overwrite',
            'layer' => $layer,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.id', $originalAsset->id)
        ->assertJsonCount(1, 'data.annotation_layer.nodes');

    $refreshed = $originalAsset->fresh();
    expect($refreshed->hasMedia(MediaAsset::SOURCE_COLLECTION))->toBeTrue()
        ->and($refreshed->source_image_url)->toContain('source=1');

    // Test that media-library.file route serves the preserved source image
    $fileResponse = $this->actingAs($fixture['field'])
        ->get(route('media-library.file', ['asset' => $refreshed, 'source' => 1]));

    $fileResponse->assertOk();
});
