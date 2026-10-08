<?php

use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\Settings\Models\AppSetting;
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

it('initiates a chunked upload for an assigned field supervisor', function (): void {
    $fixture = mediaStaffFixture();

    $response = $this->actingAs($fixture['field'])
        ->postJson(route('media-library.upload.initiate'), [
            'filename' => 'IMG_0001.jpg',
            'total_size' => 2048,
            'total_chunks' => 2,
            'mime_type' => 'image/jpeg',
            'project_id' => $fixture['project']->id,
        ])
        ->assertOk();

    expect($response->json('asset_id'))->toBeInt()
        ->and($response->json('upload_token'))->toBeString();

    $this->assertDatabaseHas('media_assets', [
        'user_id' => $fixture['field']->id,
        'market_id' => $fixture['project']->market_id,
        'project_id' => $fixture['project']->id,
        'original_filename' => 'IMG_0001.jpg',
        'mime_type' => 'image/jpeg',
        'file_size_bytes' => 2048,
        'total_chunks' => 2,
        'status' => MediaAssetStatus::PROCESSING->value,
    ]);
});

it('stores the market directly when no project is provided', function (): void {
    $fixture = mediaStaffFixture();

    $this->actingAs($fixture['field'])
        ->postJson(route('media-library.upload.initiate'), [
            'filename' => 'IMG_0002.jpg',
            'total_size' => 1024,
            'total_chunks' => 1,
            'mime_type' => 'image/png',
            'market_id' => $fixture['market']->id,
        ])
        ->assertOk();

    $this->assertDatabaseHas('media_assets', [
        'market_id' => $fixture['market']->id,
        'project_id' => null,
    ]);
});

it('allows an unassigned upload with both project and market null', function (): void {
    $fixture = mediaStaffFixture();

    $response = $this->actingAs($fixture['field'])
        ->postJson(route('media-library.upload.initiate'), [
            'filename' => 'general-photo.jpg',
            'total_size' => 1024,
            'total_chunks' => 1,
            'mime_type' => 'image/jpeg',
            'project_id' => null,
            'market_id' => null,
        ])
        ->assertOk();

    expect($response->json('asset_id'))->toBeInt()
        ->and($response->json('upload_token'))->toBeString();

    $this->assertDatabaseHas('media_assets', [
        'user_id' => $fixture['field']->id,
        'market_id' => null,
        'project_id' => null,
        'original_filename' => 'general-photo.jpg',
    ]);
});

it('rejects a mime type outside the configured allowlist', function (): void {
    $fixture = mediaStaffFixture();

    $this->actingAs($fixture['field'])
        ->postJson(route('media-library.upload.initiate'), [
            'filename' => 'notes.pdf',
            'total_size' => 1024,
            'total_chunks' => 1,
            'mime_type' => 'application/pdf',
            'market_id' => $fixture['market']->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('mime_type');
});

it('rejects a declared size above the configured maximum', function (): void {
    $fixture = mediaStaffFixture();
    $maxBytes = ((int) config('cop.media_max_file_mb')) * 1024 * 1024;

    $this->actingAs($fixture['field'])
        ->postJson(route('media-library.upload.initiate'), [
            'filename' => 'huge.jpg',
            'total_size' => $maxBytes + 1,
            'total_chunks' => 50,
            'mime_type' => 'image/jpeg',
            'market_id' => $fixture['market']->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('total_size');
});

it('forbids a field supervisor from referencing a project they are not assigned to', function (): void {
    $fixture = mediaStaffFixture();
    $outsider = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->actingAs($outsider)
        ->postJson(route('media-library.upload.initiate'), [
            'filename' => 'IMG_0003.jpg',
            'total_size' => 1024,
            'total_chunks' => 1,
            'mime_type' => 'image/jpeg',
            'project_id' => $fixture['project']->id,
        ])
        ->assertStatus(403);
});

it('rejects an initiate that exceeds the role quota', function (): void {
    $fixture = mediaStaffFixture();
    AppSetting::set('media.quota.supervisor_bytes', '2047');

    $this->actingAs($fixture['field'])
        ->postJson(route('media-library.upload.initiate'), [
            'filename' => 'IMG_0004.jpg',
            'total_size' => 2048,
            'total_chunks' => 1,
            'mime_type' => 'image/jpeg',
            'market_id' => $fixture['market']->id,
        ])
        ->assertStatus(422)
        ->assertJson(['success' => false])
        ->assertJsonPath('message', fn (string $message) => str_contains($message, 'storage limit'));
});

it('rate limits initiates to 30 per minute per user', function (): void {
    $fixture = mediaStaffFixture();

    // A tiny quota makes every request fail fast at the quota check (422)
    // without inserting rows — the throttle counts those attempts too, so
    // the 31st request is blocked with 429.
    AppSetting::set('media.quota.supervisor_bytes', '10');

    $payload = [
        'filename' => 'burst.jpg',
        'total_size' => 1024,
        'total_chunks' => 1,
        'mime_type' => 'image/jpeg',
        'market_id' => $fixture['market']->id,
    ];

    $this->actingAs($fixture['field']);

    for ($i = 0; $i < 30; $i++) {
        $this->postJson(route('media-library.upload.initiate'), $payload)->assertStatus(422);
    }

    $this->postJson(route('media-library.upload.initiate'), $payload)->assertStatus(429);

    $this->assertDatabaseCount('media_assets', 0);
});
