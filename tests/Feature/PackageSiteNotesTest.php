<?php

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Queries\PackageQuery;
use App\Domains\Markets\Models\Market;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplatePictureCategory;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Storage::fake('private');

    $this->fixture = function (): array {
        $market = Market::factory()->create();
        $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
        $manager->markets()->attach($market->id);
        $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
        $supervisor->markets()->attach($market->id);
        $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
        $project->fieldSupervisors()->attach($supervisor->id);

        $template = Template::factory()->create(['market_id' => $market->id]);

        foreach ($template->sections()->get() as $section) {
            TemplatePictureCategory::factory()->count(1)->create(['template_section_id' => $section->id]);
        }

        $this->actingAs($supervisor)
            ->post(route('packages.store', $project), [
                'project_id' => $project->id,
                'template_id' => $template->id,
            ])
            ->assertSessionHasNoErrors();

        $package = CloseoutPackage::firstOrFail();

        return compact('package', 'manager', 'supervisor', 'project');
    };
});

it('saves package site notes and exposes them to the package page', function (): void {
    $fixture = ($this->fixture)();
    $notes = 'Two hand holes were poured on the north side.';

    $this->actingAs($fixture['supervisor'])
        ->put(route('packages.notes.update', $fixture['package']), ['notes' => $notes])
        ->assertRedirect()
        ->assertSessionHas('success', 'Site notes saved.');

    expect($fixture['package']->refresh()->notes)->toBe($notes);

    $this->actingAs($fixture['supervisor'])
        ->get(route('packages.show', $fixture['package']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Packages/Show')
            ->where('package.notes', $notes)
            ->where('package.site_note_images', [])
            ->where('can_update_site_notes', true)
        );
});

it('lets the assigned manager add notes while reviewing a submitted package', function (): void {
    $fixture = ($this->fixture)();
    $fixture['package']->update(['status' => PackageStatus::SUBMITTED]);

    $this->actingAs($fixture['manager'])
        ->put(route('packages.notes.update', $fixture['package']), ['notes' => 'Verified during review.'])
        ->assertRedirect()
        ->assertSessionHas('success', 'Site notes saved.');

    expect($fixture['package']->refresh()->notes)->toBe('Verified during review.');
});

it('freezes site notes and images once the package is approved', function (): void {
    $fixture = ($this->fixture)();
    $fixture['package']->update(['status' => PackageStatus::APPROVED]);

    $this->actingAs($fixture['manager'])
        ->put(route('packages.notes.update', $fixture['package']), ['notes' => 'Should not save'])
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->actingAs($fixture['manager'])
        ->post(route('packages.notes-images.store', $fixture['package']), [
            'images' => [UploadedFile::fake()->image('late.jpg')],
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($fixture['package']->refresh()->notes)->toBeNull()
        ->and($fixture['package']->getMedia(CloseoutPackage::PACKAGE_SITE_NOTES_COLLECTION))->toHaveCount(0);

    $this->actingAs($fixture['manager'])
        ->get(route('packages.show', $fixture['package']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can_update_site_notes', false)
        );
});

it('keeps site notes read-only for the technician outside draft', function (): void {
    $fixture = ($this->fixture)();
    $fixture['package']->update(['status' => PackageStatus::UNDER_REVIEW]);

    $this->actingAs($fixture['supervisor'])
        ->put(route('packages.notes.update', $fixture['package']), ['notes' => 'Nope'])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($fixture['package']->refresh()->notes)->toBeNull();
});

it('rejects site notes longer than the character limit', function (): void {
    $fixture = ($this->fixture)();

    $this->actingAs($fixture['supervisor'])
        ->put(route('packages.notes.update', $fixture['package']), ['notes' => str_repeat('a', 20001)])
        ->assertSessionHasErrors('notes');

    expect($fixture['package']->refresh()->notes)->toBeNull();
});

it('denies outsiders and locked packages', function (): void {
    $fixture = ($this->fixture)();
    $outsider = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->actingAs($outsider)
        ->put(route('packages.notes.update', $fixture['package']), ['notes' => 'Nope'])
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');

    $this->actingAs($outsider)
        ->post(route('packages.notes-images.store', $fixture['package']), [
            'images' => [UploadedFile::fake()->image('note.jpg')],
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');

    $fixture['package']->update(['locked_at' => now(), 'locked_by' => $fixture['manager']->id]);

    $this->actingAs($fixture['supervisor'])
        ->put(route('packages.notes.update', $fixture['package']), ['notes' => 'Nope'])
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');

    expect($fixture['package']->refresh()->notes)->toBeNull();
});

it('attaches, limits, serves and removes site note images', function (): void {
    $fixture = ($this->fixture)();

    $this->actingAs($fixture['supervisor'])
        ->post(route('packages.notes-images.store', $fixture['package']), [
            'images' => [UploadedFile::fake()->image('note.jpg')],
        ])
        ->assertSessionHasNoErrors();

    $media = $fixture['package']->refresh()->getMedia(CloseoutPackage::PACKAGE_SITE_NOTES_COLLECTION);
    expect($media)->toHaveCount(1);

    $this->actingAs($fixture['supervisor'])
        ->get(route('packages.show', $fixture['package']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('package.site_note_images', 1)
        );

    $this->actingAs($fixture['supervisor'])
        ->get(route('packages.notes-images.show', [$fixture['package'], $media->first()]))
        ->assertOk();

    foreach (range(2, CloseoutPackage::PACKAGE_SITE_NOTES_IMAGE_LIMIT) as $index) {
        $fixture['package']
            ->addMedia(UploadedFile::fake()->image("note-{$index}.jpg"))
            ->toMediaCollection(CloseoutPackage::PACKAGE_SITE_NOTES_COLLECTION);
    }

    $this->actingAs($fixture['supervisor'])
        ->post(route('packages.notes-images.store', $fixture['package']), [
            'images' => [UploadedFile::fake()->image('overflow.jpg')],
        ])
        ->assertSessionHasErrors('images');

    $this->actingAs($fixture['supervisor'])
        ->delete(route('packages.notes-images.destroy', [$fixture['package'], $media->first()]))
        ->assertSessionHasNoErrors();

    expect($fixture['package']->refresh()->getMedia(CloseoutPackage::PACKAGE_SITE_NOTES_COLLECTION))
        ->toHaveCount(CloseoutPackage::PACKAGE_SITE_NOTES_IMAGE_LIMIT - 1);
    expect(Media::query()->whereKey($media->first()->id)->exists())->toBeFalse();
});

it('reports the package stored size including note images and excluding replaced photos', function (): void {
    $fixture = ($this->fixture)();

    $this->actingAs($fixture['supervisor'])
        ->post(route('packages.notes-images.store', $fixture['package']), [
            'images' => [copTestImage('image/jpeg', 800, 600)],
        ])
        ->assertSessionHasNoErrors();

    $package = app(PackageQuery::class)->show($fixture['package']->fresh());
    $size = $package->getAttribute('photos_size_bytes');

    $expected = (int) $package->getMedia(CloseoutPackage::PACKAGE_SITE_NOTES_COLLECTION)->sum('size');

    expect($size)->toBeGreaterThan(0)
        ->and($size)->toBe($expected);

    // A replaced (superseded) photo must not count toward the total.
    $selection = $package->stages->first()?->categorySelections->first();

    if ($selection !== null) {
        Photo::factory()->create([
            'closeout_package_id' => $package->id,
            'package_stage_id' => $selection->package_stage_id,
            'package_category_selection_id' => $selection->id,
            'uploaded_by' => $fixture['supervisor']->id,
            'replaced_at' => now(),
        ]);

        $after = app(PackageQuery::class)->show($package->fresh());

        expect($after->getAttribute('photos_size_bytes'))->toBe($expected);
    }
});

it('attaches a library asset into the site notes as a compressed duplicate', function (): void {
    $fixture = ($this->fixture)();

    // The library asset must belong to the package fixture's world (same
    // supervisor, same project) so the view policy allows the attach.
    $oversized = copTestImage('image/jpeg', 3000, 2000);
    $asset = mediaReadyAsset(['field' => $fixture['supervisor'], 'project' => $fixture['project']], file: $oversized);

    $this->actingAs($fixture['supervisor'])
        ->post(route('packages.notes-images.library', $fixture['package']), [
            'media_asset_ids' => [$asset->id],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $media = $fixture['package']->refresh()->getMedia(CloseoutPackage::PACKAGE_SITE_NOTES_COLLECTION);

    expect($media)->toHaveCount(1)
        ->and(copTestImageDimension($media->first()->getPath()))->toBeLessThanOrEqual(2560)
        // The Media Library master stays byte-for-byte.
        ->and(copTestImageDimension($asset->getFirstMedia(MediaAsset::COLLECTION)->getPath()))->toBe(3000);
});

it('rejects a library attach that would exceed the site-notes image limit', function (): void {
    $fixture = ($this->fixture)();
    $staff = ['field' => $fixture['supervisor'], 'project' => $fixture['project']];

    // Fill 5 of the 6 slots first.
    $attachedIds = [];
    foreach (range(1, 5) as $i) {
        $attachedIds[] = mediaReadyAsset($staff, file: copTestImage('image/jpeg', 600, 400))->id;
    }

    $this->actingAs($fixture['supervisor'])
        ->post(route('packages.notes-images.library', $fixture['package']), [
            'media_asset_ids' => $attachedIds,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    // Two more would take the total to 7 > 6.
    $overflowIds = [
        mediaReadyAsset($staff, file: copTestImage('image/jpeg', 600, 400))->id,
        mediaReadyAsset($staff, file: copTestImage('image/jpeg', 600, 400))->id,
    ];

    $this->actingAs($fixture['supervisor'])
        ->post(route('packages.notes-images.library', $fixture['package']), [
            'media_asset_ids' => $overflowIds,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors();

    expect($fixture['package']->refresh()->getMedia(CloseoutPackage::PACKAGE_SITE_NOTES_COLLECTION))
        ->toHaveCount(5);
});

it('returns a friendly message for an unsupported site-note image type', function (): void {
    $fixture = ($this->fixture)();

    $this->actingAs($fixture['supervisor'])
        ->post(route('packages.notes-images.store', $fixture['package']), [
            'images' => [UploadedFile::fake()->create('notes.txt', 100, 'text/plain')],
        ])
        ->assertSessionHasErrors();

    $errors = session('errors')->getBag('default')->all();

    expect(collect($errors)->flatten()->implode(' '))
        ->toContain('not a supported type');
});

it('downscales an oversized site note image before storing it', function (): void {
    $fixture = ($this->fixture)();

    $this->actingAs($fixture['supervisor'])
        ->post(route('packages.notes-images.store', $fixture['package']), [
            'images' => [copTestImage('image/jpeg', 3000, 2000)],
        ])
        ->assertSessionHasNoErrors();

    $media = $fixture['package']->refresh()->getMedia(CloseoutPackage::PACKAGE_SITE_NOTES_COLLECTION)->first();

    expect($media)->not->toBeNull()
        ->and(copTestImageDimension($media->getPath()))->toBeLessThanOrEqual(2560);
});

it('keeps attached note images out of subsequent package saves', function (): void {
    $fixture = ($this->fixture)();

    $this->actingAs($fixture['supervisor'])
        ->post(route('packages.notes-images.store', $fixture['package']), [
            'images' => [UploadedFile::fake()->image('note.jpg')],
        ])
        ->assertSessionHasNoErrors();

    $package = app(PackageQuery::class)
        ->show($fixture['package']->fresh());

    expect($package->getAttribute('site_note_images'))->toHaveCount(1);

    $package->update(['status' => PackageStatus::UNDER_REVIEW]);

    expect($fixture['package']->refresh()->status)->toBe(PackageStatus::UNDER_REVIEW);
});

it('will not serve or delete an image belonging to another package', function (): void {
    $fixture = ($this->fixture)();

    $other = CloseoutPackage::factory()->create(['project_id' => $fixture['project']->id]);
    $media = $other
        ->addMedia(UploadedFile::fake()->image('other.jpg'))
        ->toMediaCollection(CloseoutPackage::PACKAGE_SITE_NOTES_COLLECTION);

    $this->actingAs($fixture['supervisor'])
        ->get(route('packages.notes-images.show', [$fixture['package'], $media]))
        ->assertNotFound();

    $this->actingAs($fixture['supervisor'])
        ->delete(route('packages.notes-images.destroy', [$fixture['package'], $media]))
        ->assertNotFound();

    expect(Media::query()->whereKey($media->id)->exists())->toBeTrue();
});
