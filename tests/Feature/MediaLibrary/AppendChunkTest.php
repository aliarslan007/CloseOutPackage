<?php

use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\MediaLibrary\Services\ChunkStorageService;
use App\Domains\MediaLibrary\Services\UploadTokenService;
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

/**
 * @param  array{market: mixed, project: mixed, manager: mixed, field: mixed, admin: mixed}  $fixture
 * @return array{asset: MediaAsset, token: string}
 */
function initiatedAsset(array $fixture): array
{
    $response = test()->actingAs($fixture['field'])
        ->postJson(route('media-library.upload.initiate'), [
            'filename' => 'photo.jpg',
            'total_size' => 2048,
            'total_chunks' => 2,
            'mime_type' => 'image/jpeg',
            'project_id' => $fixture['project']->id,
        ])
        ->assertOk();

    return [
        'asset' => MediaAsset::query()->findOrFail($response->json('asset_id')),
        'token' => $response->json('upload_token'),
    ];
}

it('stores chunks for an in-flight upload', function (): void {
    $fixture = mediaStaffFixture();
    $setup = initiatedAsset($fixture);

    $this->actingAs($fixture['field'])
        ->put(route('media-library.upload.chunk', $setup['asset']), [
            'chunk' => mediaByteFile('part0.bin', 1024),
            'chunk_index' => 0,
            'total_chunks' => 2,
        ], mediaJsonHeaders($setup['token']))
        ->assertOk();

    $this->actingAs($fixture['field'])
        ->put(route('media-library.upload.chunk', $setup['asset']), [
            'chunk' => mediaByteFile('part1.bin', 1024),
            'chunk_index' => 1,
            'total_chunks' => 2,
        ], mediaJsonHeaders($setup['token']))
        ->assertOk();

    expect(app(ChunkStorageService::class)->countChunks($setup['asset']->id))->toBe(2);
});

it('accepts chunk uploads via POST request', function (): void {
    $fixture = mediaStaffFixture();
    $setup = initiatedAsset($fixture);

    $this->actingAs($fixture['field'])
        ->post(route('media-library.upload.chunk', $setup['asset']), [
            'chunk' => mediaByteFile('part0.bin', 1024),
            'chunk_index' => 0,
            'total_chunks' => 2,
        ], mediaJsonHeaders($setup['token']))
        ->assertOk();

    expect(app(ChunkStorageService::class)->hasChunk($setup['asset']->id, 0))->toBeTrue();
});

it('treats a duplicate chunk index as idempotent without rewriting', function (): void {
    $fixture = mediaStaffFixture();
    $setup = initiatedAsset($fixture);

    $this->actingAs($fixture['field'])
        ->put(route('media-library.upload.chunk', $setup['asset']), [
            'chunk' => mediaByteFile('part0.bin', 1024),
            'chunk_index' => 0,
            'total_chunks' => 2,
        ], mediaJsonHeaders($setup['token']))
        ->assertOk();

    $this->actingAs($fixture['field'])
        ->put(route('media-library.upload.chunk', $setup['asset']), [
            'chunk' => mediaByteFile('part0-again.bin', 3072),
            'chunk_index' => 0,
            'total_chunks' => 2,
        ], mediaJsonHeaders($setup['token']))
        ->assertOk();

    $stored = Storage::disk('private')->get(
        'media-library/chunks/'.$setup['asset']->id.'/000000',
    );

    expect($stored)->not->toBeNull()
        ->and(strlen((string) $stored))->toBe(1024)
        ->and(app(ChunkStorageService::class)->countChunks($setup['asset']->id))->toBe(1);
});

it('rejects a chunk index outside the declared range', function (): void {
    $fixture = mediaStaffFixture();
    $setup = initiatedAsset($fixture);

    $this->actingAs($fixture['field'])
        ->put(route('media-library.upload.chunk', $setup['asset']), [
            'chunk' => mediaByteFile('part9.bin', 1024),
            'chunk_index' => 2,
            'total_chunks' => 2,
        ], mediaJsonHeaders($setup['token']))
        ->assertStatus(422)
        ->assertJson(['success' => false])
        ->assertJsonPath('message', 'Chunk index out of range.');
});

it('returns 401 without a valid upload token', function (): void {
    $fixture = mediaStaffFixture();
    $setup = initiatedAsset($fixture);

    $this->actingAs($fixture['field'])
        ->put(route('media-library.upload.chunk', $setup['asset']), [
            'chunk' => mediaByteFile('part0.bin', 1024),
            'chunk_index' => 0,
            'total_chunks' => 2,
        ], mediaJsonHeaders())
        ->assertStatus(401);
});

it('returns 401 when the token belongs to a different asset', function (): void {
    $fixture = mediaStaffFixture();
    $setup = initiatedAsset($fixture);
    $other = MediaAsset::factory()->create([
        'user_id' => $fixture['field']->id,
        'market_id' => $fixture['market']->id,
        'project_id' => $fixture['project']->id,
        'status' => MediaAssetStatus::PROCESSING,
    ]);

    $this->actingAs($fixture['field'])
        ->put(route('media-library.upload.chunk', $other), [
            'chunk' => mediaByteFile('part0.bin', 1024),
            'chunk_index' => 0,
            'total_chunks' => 1,
        ], mediaJsonHeaders($setup['token']))
        ->assertStatus(401);
});

it('returns 409 when the upload was already finalized', function (): void {
    $fixture = mediaStaffFixture();
    $asset = MediaAsset::factory()->create([
        'user_id' => $fixture['field']->id,
        'market_id' => $fixture['market']->id,
        'project_id' => $fixture['project']->id,
        'status' => MediaAssetStatus::READY,
        'total_chunks' => 1,
    ]);
    $token = app(UploadTokenService::class)->issue($asset->id, $fixture['field']->id);

    $this->actingAs($fixture['field'])
        ->put(route('media-library.upload.chunk', $asset), [
            'chunk' => mediaByteFile('part0.bin', 1024),
            'chunk_index' => 0,
            'total_chunks' => 1,
        ], mediaJsonHeaders($token))
        ->assertStatus(409)
        ->assertJsonPath('message', 'Upload already completed.');
});

it('returns 403 when a non-owner attempts to append a chunk', function (): void {
    $fixture = mediaStaffFixture();
    $setup = initiatedAsset($fixture);
    $outsider = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->actingAs($outsider)
        ->put(route('media-library.upload.chunk', $setup['asset']), [
            'chunk' => mediaByteFile('part0.bin', 1024),
            'chunk_index' => 0,
            'total_chunks' => 2,
        ], mediaJsonHeaders())
        ->assertStatus(403);
});

it('returns 507 when the chunk cannot be persisted', function (): void {
    $fixture = mediaStaffFixture();
    $setup = initiatedAsset($fixture);

    // ChunkStorageService is final, so force the failure at the disk adapter:
    // the adapter reports a failed write and the service maps it to 507.
    $disk = Storage::disk('private');
    $failingDisk = Mockery::mock($disk);
    $failingDisk->shouldReceive('putFileAs')->andReturn(false);
    Storage::shouldReceive('disk')->andReturn($failingDisk);

    $this->actingAs($fixture['field'])
        ->put(route('media-library.upload.chunk', $setup['asset']), [
            'chunk' => mediaByteFile('part0.bin', 1024),
            'chunk_index' => 0,
            'total_chunks' => 2,
        ], mediaJsonHeaders($setup['token']))
        ->assertStatus(507)
        ->assertJson(['success' => false])
        ->assertJsonPath('message', 'Server storage is temporarily unavailable. Try again later.');
});
