<?php

declare(strict_types=1);

use App\Domains\ChangeOrders\Enums\ChangeOrderStatus;
use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\ChangeOrders\Models\ChangeOrderPhoto;
use App\Domains\ChangeOrders\Models\ChangeOrderRecipient;
use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\Markets\Models\Market;
use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Storage::fake('private');
});

function createTestMediaAsset(User $user, Project $project, string $filename = 'test-photo.jpg'): MediaAsset
{
    $asset = MediaAsset::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'market_id' => $project->market_id,
        'original_filename' => $filename,
        'mime_type' => 'image/jpeg',
        'status' => MediaAssetStatus::READY,
    ]);

    $file = UploadedFile::fake()->image($filename, 800, 600);
    $asset->addMedia($file)->preservingOriginal()->toMediaCollection(MediaAsset::COLLECTION);

    return $asset;
}

function validChangeOrderPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Structural Repair',
        'description' => 'Unforeseen tower frame damage',
        'items' => [
            [
                'description' => 'Welding service',
                'vendor_name' => 'Weld Corp',
                'category' => 'Labor',
                'amount' => 750.00,
                'is_included' => true,
            ],
        ],
        'recipient_ids' => [],
        'media_asset_ids' => [],
    ], $overrides);
}

it('attaches photos when creating a change order with media_asset_ids', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);

    $asset = createTestMediaAsset($manager, $project, 'damage-photo.jpg');

    $response = $this->actingAs($manager)
        ->post(route('projects.change-orders.store', $project), validChangeOrderPayload([
            'media_asset_ids' => [$asset->id],
        ]));

    $response->assertRedirect(route('projects.change-orders.index', $project));

    $changeOrder = ChangeOrder::query()->where('project_id', $project->id)->firstOrFail();
    expect($changeOrder->photos()->count())->toBe(1);

    $photo = $changeOrder->photos()->first();
    expect($photo->media_asset_id)->toBe($asset->id)
        ->and($photo->uploaded_by)->toBe($manager->id)
        ->and($photo->original_name)->toBe('damage-photo.jpg')
        ->and($photo->mime_type)->toBe('image/jpeg')
        ->and(Storage::disk('private')->exists($photo->path))->toBeTrue();
});

it('attaches additional photos when updating a change order', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);

    $asset1 = createTestMediaAsset($manager, $project, 'first.jpg');
    $asset2 = createTestMediaAsset($manager, $project, 'second.jpg');

    $changeOrder = ChangeOrder::factory()->for($project)->create(['created_by' => $manager->id]);

    $this->actingAs($manager)
        ->put(route('projects.change-orders.update', [$project, $changeOrder]), validChangeOrderPayload([
            'media_asset_ids' => [$asset1->id, $asset2->id],
        ]))
        ->assertRedirect();

    expect($changeOrder->photos()->count())->toBe(2);
});

it('attaches photos via the standalone photos store endpoint', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);

    $asset = createTestMediaAsset($manager, $project, 'evidence.jpg');
    $changeOrder = ChangeOrder::factory()->for($project)->create(['created_by' => $manager->id]);

    $response = $this->actingAs($manager)
        ->postJson(route('projects.change-orders.photos.store', [$project, $changeOrder]), [
            'media_asset_ids' => [$asset->id],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('count', 1);

    expect($changeOrder->photos()->count())->toBe(1);
});

it('detaches a photo via the photos destroy endpoint', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);

    $asset = createTestMediaAsset($manager, $project, 'to-delete.jpg');
    $changeOrder = ChangeOrder::factory()->for($project)->create(['created_by' => $manager->id]);

    $this->actingAs($manager)
        ->postJson(route('projects.change-orders.photos.store', [$project, $changeOrder]), [
            'media_asset_ids' => [$asset->id],
        ]);

    $photo = $changeOrder->photos()->firstOrFail();
    $diskPath = $photo->path;
    expect(Storage::disk('private')->exists($diskPath))->toBeTrue();

    $response = $this->actingAs($manager)
        ->delete(route('projects.change-orders.photos.destroy', [$project, $changeOrder, $photo]));

    $response->assertNoContent();
    expect($changeOrder->photos()->count())->toBe(0)
        ->and(Storage::disk('private')->exists($diskPath))->toBeFalse();
});

it('denies attaching photos to a sent change order', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);

    $asset = createTestMediaAsset($manager, $project);
    $sentChangeOrder = ChangeOrder::factory()->sent()->for($project)->create(['created_by' => $manager->id]);

    $this->actingAs($manager)
        ->postJson(route('projects.change-orders.photos.store', [$project, $sentChangeOrder]), [
            'media_asset_ids' => [$asset->id],
        ])
        ->assertForbidden();
});

it('allows staff to stream photos', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);

    $asset = createTestMediaAsset($manager, $project, 'stream-test.jpg');
    $changeOrder = ChangeOrder::factory()->for($project)->create(['created_by' => $manager->id]);

    $this->actingAs($manager)
        ->postJson(route('projects.change-orders.photos.store', [$project, $changeOrder]), [
            'media_asset_ids' => [$asset->id],
        ]);

    $photo = $changeOrder->photos()->firstOrFail();

    $response = $this->actingAs($manager)
        ->get(route('change-orders.photos.stream', $photo));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('image/jpeg');
});

it('allows a recipient customer user to stream photos', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);

    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    CustomerProjectAccess::factory()->create(['user_id' => $customer->id, 'project_id' => $project->id]);

    $asset = createTestMediaAsset($manager, $project);
    $changeOrder = ChangeOrder::factory()->for($project)->create(['created_by' => $manager->id]);

    $this->actingAs($manager)
        ->postJson(route('projects.change-orders.photos.store', [$project, $changeOrder]), [
            'media_asset_ids' => [$asset->id],
        ])
        ->assertCreated();

    $changeOrder->update(['status' => ChangeOrderStatus::SENT_TO_CUSTOMER]);

    ChangeOrderRecipient::create([
        'change_order_id' => $changeOrder->id,
        'user_id' => $customer->id,
    ]);

    $photo = $changeOrder->photos()->firstOrFail();

    $this->actingAs($customer)
        ->get(route('change-orders.photos.stream', $photo))
        ->assertOk();
});

it('allows external viewer with a valid token to stream photos without authentication', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);

    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    $asset = createTestMediaAsset($manager, $project);
    $changeOrder = ChangeOrder::factory()->for($project)->create(['created_by' => $manager->id]);

    $rawToken = ChangeOrderRecipient::generateToken();
    ChangeOrderRecipient::create([
        'change_order_id' => $changeOrder->id,
        'user_id' => $customer->id,
        'token_hash' => ChangeOrderRecipient::hashToken($rawToken),
        'token_expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($manager)
        ->postJson(route('projects.change-orders.photos.store', [$project, $changeOrder]), [
            'media_asset_ids' => [$asset->id],
        ]);

    $photo = $changeOrder->photos()->firstOrFail();

    auth()->logout();

    // Guest request with valid token
    $this->get(route('change-orders.photos.stream', ['photo' => $photo, 'token' => $rawToken]))
        ->assertOk();
});

it('blocks streaming photos with an expired token or no access', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);

    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    $asset = createTestMediaAsset($manager, $project);
    $changeOrder = ChangeOrder::factory()->for($project)->create(['created_by' => $manager->id]);

    $rawToken = ChangeOrderRecipient::generateToken();
    ChangeOrderRecipient::create([
        'change_order_id' => $changeOrder->id,
        'user_id' => $customer->id,
        'token_hash' => ChangeOrderRecipient::hashToken($rawToken),
        'token_expires_at' => now()->subDay(), // Expired
    ]);

    $this->actingAs($manager)
        ->postJson(route('projects.change-orders.photos.store', [$project, $changeOrder]), [
            'media_asset_ids' => [$asset->id],
        ]);

    $photo = $changeOrder->photos()->firstOrFail();

    auth()->logout();

    // Guest request with expired token should be forbidden
    $this->get(route('change-orders.photos.stream', ['photo' => $photo, 'token' => $rawToken]))
        ->assertForbidden();

    // Guest request with no token should be forbidden
    $this->get(route('change-orders.photos.stream', $photo))
        ->assertForbidden();

    // Random customer from another company should be forbidden
    $outsider = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    $this->actingAs($outsider)
        ->get(route('change-orders.photos.stream', $photo))
        ->assertForbidden();
});

it('detaches removed photos and deletes their files when updating a change order with fewer photo_ids', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);

    $asset1 = createTestMediaAsset($manager, $project, 'photo1.jpg');
    $asset2 = createTestMediaAsset($manager, $project, 'photo2.jpg');

    $changeOrder = ChangeOrder::factory()->for($project)->create(['created_by' => $manager->id]);

    $this->actingAs($manager)
        ->put(route('projects.change-orders.update', [$project, $changeOrder]), validChangeOrderPayload([
            'media_asset_ids' => [$asset1->id, $asset2->id],
        ]))
        ->assertRedirect();

    expect($changeOrder->photos()->count())->toBe(2);

    $photo1 = $changeOrder->photos()->where('original_name', 'photo1.jpg')->firstOrFail();
    $photo2 = $changeOrder->photos()->where('original_name', 'photo2.jpg')->firstOrFail();

    expect(Storage::disk('private')->exists($photo1->path))->toBeTrue()
        ->and(Storage::disk('private')->exists($photo2->path))->toBeTrue();

    // Manager removes photo2 in the edit wizard, keeping only photo1
    $this->actingAs($manager)
        ->put(route('projects.change-orders.update', [$project, $changeOrder]), validChangeOrderPayload([
            'photo_ids' => [$photo1->id],
            'media_asset_ids' => [],
        ]))
        ->assertRedirect();

    $changeOrder->refresh();
    expect($changeOrder->photos()->count())->toBe(1)
        ->and($changeOrder->photos()->where('id', $photo1->id)->exists())->toBeTrue()
        ->and($changeOrder->photos()->where('id', $photo2->id)->exists())->toBeFalse()
        ->and(Storage::disk('private')->exists($photo1->path))->toBeTrue()
        ->and(Storage::disk('private')->exists($photo2->path))->toBeFalse();
});

it('detaches all photos when updating a change order with empty photo_ids', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);

    $asset = createTestMediaAsset($manager, $project, 'alone.jpg');
    $changeOrder = ChangeOrder::factory()->for($project)->create(['created_by' => $manager->id]);

    $this->actingAs($manager)
        ->put(route('projects.change-orders.update', [$project, $changeOrder]), validChangeOrderPayload([
            'media_asset_ids' => [$asset->id],
        ]))
        ->assertRedirect();

    $photo = $changeOrder->photos()->firstOrFail();
    $diskPath = $photo->path;
    expect(Storage::disk('private')->exists($diskPath))->toBeTrue();

    // Remove all photos
    $this->actingAs($manager)
        ->put(route('projects.change-orders.update', [$project, $changeOrder]), validChangeOrderPayload([
            'photo_ids' => [],
            'media_asset_ids' => [],
        ]))
        ->assertRedirect();

    $changeOrder->refresh();
    expect($changeOrder->photos()->count())->toBe(0)
        ->and(Storage::disk('private')->exists($diskPath))->toBeFalse();
});

it('preserves existing photos when photo_ids is omitted on update', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);

    $asset = createTestMediaAsset($manager, $project, 'preserved.jpg');
    $changeOrder = ChangeOrder::factory()->for($project)->create(['created_by' => $manager->id]);

    $this->actingAs($manager)
        ->put(route('projects.change-orders.update', [$project, $changeOrder]), validChangeOrderPayload([
            'media_asset_ids' => [$asset->id],
        ]))
        ->assertRedirect();

    $photo = $changeOrder->photos()->firstOrFail();

    // Update title without photo_ids key
    $this->actingAs($manager)
        ->put(route('projects.change-orders.update', [$project, $changeOrder]), validChangeOrderPayload([
            'title' => 'Updated Title Only',
        ]))
        ->assertRedirect();

    $changeOrder->refresh();
    expect($changeOrder->title)->toBe('Updated Title Only')
        ->and($changeOrder->photos()->count())->toBe(1)
        ->and(Storage::disk('private')->exists($photo->path))->toBeTrue();
});

it('keeps existing photo and attaches new photo simultaneously on update', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);

    $asset1 = createTestMediaAsset($manager, $project, 'photo1.jpg');
    $asset2 = createTestMediaAsset($manager, $project, 'photo2.jpg');
    $asset3 = createTestMediaAsset($manager, $project, 'photo3.jpg');

    $changeOrder = ChangeOrder::factory()->for($project)->create(['created_by' => $manager->id]);

    // Initially attach photo1 and photo2
    $this->actingAs($manager)
        ->put(route('projects.change-orders.update', [$project, $changeOrder]), validChangeOrderPayload([
            'media_asset_ids' => [$asset1->id, $asset2->id],
        ]))
        ->assertRedirect();

    $photo1 = $changeOrder->photos()->where('original_name', 'photo1.jpg')->firstOrFail();
    $photo2 = $changeOrder->photos()->where('original_name', 'photo2.jpg')->firstOrFail();

    // Update: Keep photo1, remove photo2, add asset3
    $this->actingAs($manager)
        ->put(route('projects.change-orders.update', [$project, $changeOrder]), validChangeOrderPayload([
            'photo_ids' => [$photo1->id],
            'media_asset_ids' => [$asset3->id],
        ]))
        ->assertRedirect();

    $changeOrder->refresh();
    expect($changeOrder->photos()->count())->toBe(2)
        ->and($changeOrder->photos()->where('id', $photo1->id)->exists())->toBeTrue()
        ->and($changeOrder->photos()->where('id', $photo2->id)->exists())->toBeFalse()
        ->and($changeOrder->photos()->where('original_name', 'photo3.jpg')->exists())->toBeTrue()
        ->and(Storage::disk('private')->exists($photo2->path))->toBeFalse();
});

/**
 * Seeds already-attached photo rows without media files. The cap rule only
 * reads change_order_photos, which keeps the limit tests fast.
 */
function seedChangeOrderPhotos(ChangeOrder $changeOrder, User $user, int $count): void
{
    for ($i = 1; $i <= $count; $i++) {
        ChangeOrderPhoto::create([
            'change_order_id' => $changeOrder->id,
            'uploaded_by' => $user->id,
            'original_name' => "existing-{$i}.jpg",
            'path' => "change-orders/{$changeOrder->id}/existing-{$i}.jpg",
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
        ]);
    }
}

/** @return array{0: User, 1: Project, 2: ChangeOrder} */
function changeOrderFixture(): array
{
    $market = Market::factory()->create(['is_active' => true]);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
    $changeOrder = ChangeOrder::factory()->for($project)->create(['created_by' => $manager->id]);

    return [$manager, $project, $changeOrder];
}

it('rejects attaching a photo once the change order reaches the photo limit', function (): void {
    [$manager, $project, $changeOrder] = changeOrderFixture();
    seedChangeOrderPhotos($changeOrder, $manager, ChangeOrder::MAX_PHOTOS);

    $asset = createTestMediaAsset($manager, $project, 'overflow.jpg');

    $this->actingAs($manager)
        ->put(route('projects.change-orders.update', [$project, $changeOrder]), validChangeOrderPayload([
            'media_asset_ids' => [$asset->id],
        ]))
        ->assertSessionHasErrors('media_asset_ids');

    expect($changeOrder->photos()->count())->toBe(ChangeOrder::MAX_PHOTOS);
});

it('allows swapping a photo while sitting at the photo limit', function (): void {
    [$manager, $project, $changeOrder] = changeOrderFixture();
    seedChangeOrderPhotos($changeOrder, $manager, ChangeOrder::MAX_PHOTOS);

    $dropped = $changeOrder->photos()->firstOrFail();
    $keptIds = $changeOrder->photos()->whereKeyNot($dropped->id)->pluck('id')->all();
    $asset = createTestMediaAsset($manager, $project, 'replacement.jpg');

    $this->actingAs($manager)
        ->put(route('projects.change-orders.update', [$project, $changeOrder]), validChangeOrderPayload([
            'photo_ids' => $keptIds,
            'media_asset_ids' => [$asset->id],
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($changeOrder->photos()->count())->toBe(ChangeOrder::MAX_PHOTOS)
        ->and($changeOrder->photos()->where('original_name', 'replacement.jpg')->exists())->toBeTrue()
        ->and($changeOrder->photos()->whereKey($dropped->id)->exists())->toBeFalse();
});

it('does not double-count a library asset that is already attached', function (): void {
    [$manager, $project, $changeOrder] = changeOrderFixture();
    seedChangeOrderPhotos($changeOrder, $manager, ChangeOrder::MAX_PHOTOS - 1);

    $asset = createTestMediaAsset($manager, $project, 'already-attached.jpg');

    $this->actingAs($manager)
        ->put(route('projects.change-orders.update', [$project, $changeOrder]), validChangeOrderPayload([
            'media_asset_ids' => [$asset->id],
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    // Re-sending the same attached asset must not read as a 21st photo.
    $this->actingAs($manager)
        ->put(route('projects.change-orders.update', [$project, $changeOrder]), validChangeOrderPayload([
            'media_asset_ids' => [$asset->id],
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($changeOrder->photos()->count())->toBe(ChangeOrder::MAX_PHOTOS);
});

it('rejects the standalone attach endpoint when the photo limit is reached', function (): void {
    [$manager, $project, $changeOrder] = changeOrderFixture();
    seedChangeOrderPhotos($changeOrder, $manager, ChangeOrder::MAX_PHOTOS);

    $asset = createTestMediaAsset($manager, $project, 'endpoint-overflow.jpg');

    $this->actingAs($manager)
        ->postJson(route('projects.change-orders.photos.store', [$project, $changeOrder]), [
            'media_asset_ids' => [$asset->id],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('media_asset_ids');

    expect($changeOrder->photos()->count())->toBe(ChangeOrder::MAX_PHOTOS);
});

it('passes the photo limit to the show page so the client clamp matches the server cap', function (): void {
    [$manager, $project, $changeOrder] = changeOrderFixture();

    $this->actingAs($manager)
        ->get(route('projects.change-orders.show', [$project, $changeOrder]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/ChangeOrders/Show')
            ->where('photoLimit', ChangeOrder::MAX_PHOTOS));
});

it('compresses oversized media library photos when attaching to a change order', function (): void {
    [$manager, $project, $changeOrder] = changeOrderFixture();

    $asset = MediaAsset::factory()->create([
        'user_id' => $manager->id,
        'project_id' => $project->id,
        'market_id' => $project->market_id,
        'original_filename' => 'giant.jpg',
        'mime_type' => 'image/jpeg',
        'status' => MediaAssetStatus::READY,
    ]);
    $giantFile = UploadedFile::fake()->image('giant.jpg', 3000, 2000);
    $media = $asset->addMedia($giantFile)->preservingOriginal()->toMediaCollection(MediaAsset::COLLECTION);

    $this->actingAs($manager)
        ->postJson(route('projects.change-orders.photos.store', [$project, $changeOrder]), [
            'media_asset_ids' => [$asset->id],
        ])
        ->assertStatus(201);

    $photo = $changeOrder->photos()->where('media_asset_id', $asset->id)->firstOrFail();
    $privateDisk = Storage::disk('private');
    expect($privateDisk->exists($photo->path))->toBeTrue();

    // Verify compressed photo on private disk is downscaled to <= 2560px
    $storedContent = $privateDisk->get($photo->path);
    $tmpPath = tempnam(sys_get_temp_dir(), 'test_co_');
    file_put_contents($tmpPath, $storedContent);
    $info = getimagesize($tmpPath);
    @unlink($tmpPath);

    expect(max($info[0], $info[1]))->toBeLessThanOrEqual(2560);

    // Verify the master media asset is untouched and remains 3000x2000
    $masterMedia = $asset->fresh()->getFirstMedia(MediaAsset::COLLECTION);
    expect($masterMedia)->not->toBeNull();
    $masterInfo = getimagesize($masterMedia->getPath());
    expect(max($masterInfo[0], $masterInfo[1]))->toBe(3000);
});
