<?php

use App\Domains\Markets\Models\Market;
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

it('uploads a site photo for a project', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create();

    $this->actingAs($admin)
        ->post(route('projects.site-photo.store', $project), [
            'photo' => UploadedFile::fake()->image('site.jpg'),
        ])
        ->assertRedirect(route('projects.show', $project))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('media', [
        'model_type' => Project::class,
        'model_id' => $project->id,
        'collection_name' => Project::SITE_PHOTO_COLLECTION,
    ]);
});

it('rejects a site photo upload without a file', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create();

    $this->actingAs($admin)
        ->post(route('projects.site-photo.store', $project))
        ->assertSessionHasErrors('photo');
});

it('serves the site photo to authorized viewers', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create();

    $project->addMedia(UploadedFile::fake()->image('site.jpg'))->toMediaCollection(Project::SITE_PHOTO_COLLECTION);

    $this->actingAs($admin)
        ->get(route('projects.site-photo.show', $project))
        ->assertOk()
        ->assertHeader('content-type', 'image/jpeg');
});

it('returns 404 when no site photo exists', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create();

    $this->actingAs($admin)
        ->get(route('projects.site-photo.show', $project))
        ->assertNotFound();
});

it('replaces the site photo and serves a fresh cache-busted url', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create();

    $this->actingAs($admin)
        ->post(route('projects.site-photo.store', $project), [
            'photo' => UploadedFile::fake()->image('site.jpg'),
        ])
        ->assertSessionHas('success');

    $first = $project->getFirstMedia(Project::SITE_PHOTO_COLLECTION);

    expect($first)->not->toBeNull();

    $this->actingAs($admin)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sitePhotoUrl', route('projects.site-photo.show', $project).'?v='.$first->id));

    $this->actingAs($admin)
        ->post(route('projects.site-photo.store', $project), [
            'photo' => UploadedFile::fake()->image('replacement.jpg'),
        ])
        ->assertSessionHas('success');

    $second = $project->fresh()->getFirstMedia(Project::SITE_PHOTO_COLLECTION);

    // The replacement must be a different media record, so the url changes
    // and the browser cannot serve a stale cached image.
    expect($second)->not->toBeNull()
        ->and($second->id)->not->toBe($first->id);

    $this->actingAs($admin)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sitePhotoUrl', route('projects.site-photo.show', $project).'?v='.$second->id));
});

it('denies site photo upload to field supervisors', function (): void {
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create();

    $this->actingAs($field)
        ->post(route('projects.site-photo.store', $project), [
            'photo' => UploadedFile::fake()->image('site.jpg'),
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');
});

it('attaches an optional site photo while creating a project', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();

    $this->actingAs($admin)
        ->post(route('projects.store'), [
            'market_id' => $market->id,
            'name' => 'Lomas Peak Site',
            'address' => '123 Main St',
            'completed_date' => '2026-12-01',
            'awarded_date' => '2026-06-01',
            'rec_issued_date' => '2026-06-05',
            'site_photo' => UploadedFile::fake()->image('site.jpg'),
        ])
        ->assertRedirect(route('projects.index'))
        ->assertSessionHas('success');

    $project = Project::where('name', 'Lomas Peak Site')->firstOrFail();

    $this->assertDatabaseHas('media', [
        'model_type' => Project::class,
        'model_id' => $project->id,
        'collection_name' => Project::SITE_PHOTO_COLLECTION,
    ]);
});

it('leaves a project without a site photo when the field is omitted', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();

    $this->actingAs($admin)
        ->post(route('projects.store'), [
            'market_id' => $market->id,
            'name' => 'No Photo Site',
            'address' => '123 Main St',
            'completed_date' => '2026-12-01',
            'awarded_date' => '2026-06-01',
            'rec_issued_date' => '2026-06-05',
        ])
        ->assertRedirect(route('projects.index'));

    expect(Project::where('name', 'No Photo Site')->firstOrFail()->getFirstMedia(Project::SITE_PHOTO_COLLECTION))
        ->toBeNull();
});

it('replaces the site photo from the project edit form', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create();
    $project->addMedia(UploadedFile::fake()->image('site.jpg'))->toMediaCollection(Project::SITE_PHOTO_COLLECTION);
    $first = $project->getFirstMedia(Project::SITE_PHOTO_COLLECTION);

    // Mirrors the browser: the form posts multipart with _method=put, because
    // PHP only populates $_POST/$_FILES for POST requests.
    $this->actingAs($admin)
        ->post(route('projects.update', $project), [
            '_method' => 'put',
            'name' => 'Renamed Site',
            'completed_date' => '2026-07-01',
            'site_photo' => UploadedFile::fake()->image('replacement.jpg'),
        ])
        ->assertRedirect(route('projects.show', $project))
        ->assertSessionHas('success');

    expect($project->fresh()->name)->toBe('Renamed Site');

    $second = $project->fresh()->getFirstMedia(Project::SITE_PHOTO_COLLECTION);

    // singleFile collection: the old media is gone, not stacked.
    expect($project->fresh()->getMedia(Project::SITE_PHOTO_COLLECTION))->toHaveCount(1)
        ->and($second->id)->not->toBe($first->id);
});
