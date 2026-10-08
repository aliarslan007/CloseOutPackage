<?php

use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\MediaLibrary\Services\UploadTokenService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Storage::fake('private');
});

/**
 * Upload every chunk of a real image so finalize can reassemble it back into
 * the exact declared file.
 *
 * @param  array{market: mixed, project: mixed, manager: mixed, field: mixed, admin: mixed}  $fixture
 * @return array{asset: MediaAsset, token: string, parts: int}
 */
function completedUpload(array $fixture, int $parts = 2): array
{
    $image = UploadedFile::fake()->image('photo.jpg', 1200, 800);
    $split = splitFileIntoChunks($image, $parts);

    $response = test()->actingAs($fixture['field'])
        ->postJson(route('media-library.upload.initiate'), [
            'filename' => 'photo.jpg',
            'total_size' => $split['bytes'],
            'total_chunks' => $parts,
            'mime_type' => 'image/jpeg',
            'project_id' => $fixture['project']->id,
        ])
        ->assertOk();

    $asset = MediaAsset::query()->findOrFail($response->json('asset_id'));
    $token = $response->json('upload_token');

    foreach ($split['files'] as $index => $file) {
        test()->actingAs($fixture['field'])
            ->put(route('media-library.upload.chunk', $asset), [
                'chunk' => $file,
                'chunk_index' => $index,
                'total_chunks' => $parts,
            ], mediaJsonHeaders($token))
            ->assertOk();
    }

    return ['asset' => $asset, 'token' => $token, 'parts' => $parts];
}

/**
 * @return TestResponse
 */
function finalizeJson(array $fixture, MediaAsset $asset, string $token)
{
    return test()->actingAs($fixture['field'])
        ->postJson(route('media-library.upload.finalize', $asset), [], mediaJsonHeaders($token));
}

it('processes the upload to ready once every chunk is present', function (): void {
    $fixture = mediaStaffFixture();
    $setup = completedUpload($fixture);

    Cache::put('media.quota.used:'.$fixture['field']->id, 99999);

    finalizeJson($fixture, $setup['asset'], $setup['token'])
        ->assertOk()
        ->assertJson([
            'asset_id' => $setup['asset']->id,
            'status' => MediaAssetStatus::READY->value,
        ]);

    $asset = $setup['asset']->fresh();

    expect($asset->status)->toBe(MediaAssetStatus::READY)
        ->and($asset->getMedia(MediaAsset::COLLECTION))->toHaveCount(1)
        ->and(Cache::has('media.quota.used:'.$fixture['field']->id))->toBeFalse();

    Storage::disk('private')->assertMissing('media-library/chunks/'.$asset->id);
});

it('returns 422 and stays processing while chunks are missing', function (): void {
    $fixture = mediaStaffFixture();
    $image = UploadedFile::fake()->image('photo.jpg', 800, 600);
    $split = splitFileIntoChunks($image, 2);

    $response = $this->actingAs($fixture['field'])
        ->postJson(route('media-library.upload.initiate'), [
            'filename' => 'photo.jpg',
            'total_size' => $split['bytes'],
            'total_chunks' => 2,
            'mime_type' => 'image/jpeg',
            'project_id' => $fixture['project']->id,
        ])
        ->assertOk();

    $asset = MediaAsset::query()->findOrFail($response->json('asset_id'));
    $token = $response->json('upload_token');

    $this->actingAs($fixture['field'])
        ->put(route('media-library.upload.chunk', $asset), [
            'chunk' => $split['files'][0],
            'chunk_index' => 0,
            'total_chunks' => 2,
        ], mediaJsonHeaders($token))
        ->assertOk();

    finalizeJson($fixture, $asset, $token)
        ->assertStatus(422)
        ->assertJson(['success' => false])
        ->assertJsonPath('message', '1 of 2 parts received.');

    expect($asset->fresh()->status)->toBe(MediaAssetStatus::PROCESSING);
});

it('returns 409 when finalizing an already-completed upload', function (): void {
    $fixture = mediaStaffFixture();
    $setup = completedUpload($fixture);

    finalizeJson($fixture, $setup['asset'], $setup['token'])->assertOk();

    finalizeJson($fixture, $setup['asset'], $setup['token'])
        ->assertStatus(409)
        ->assertJsonPath('message', 'Upload already completed.');
});

it('returns 401 when the upload token has expired', function (): void {
    $fixture = mediaStaffFixture();
    $setup = completedUpload($fixture);

    config()->set('cop.media_upload_token_ttl', -1);
    $expired = app(UploadTokenService::class)->issue($setup['asset']->id, $fixture['field']->id);

    finalizeJson($fixture, $setup['asset'], $expired)
        ->assertStatus(401)
        ->assertJsonPath('message', 'Your upload session has expired. Please re-select your files and try again.');
});
