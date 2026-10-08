<?php

declare(strict_types=1);

use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Services\TemplateCloningService;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->adminUser = fn (): User => User::factory()->create()->assignRole(Role::ADMIN->value);
});

it('saves and trims the additional site notes', function (): void {
    $template = Template::factory()->create();

    $this->actingAs(($this->adminUser)())
        ->put(route('admin.templates.additional-notes.update', $template), [
            'notes' => '  Two hand holes were poured on the north side.  ',
        ])
        ->assertSessionHasNoErrors();

    expect($template->refresh()->additional_site_notes)
        ->toBe('Two hand holes were poured on the north side.');
});

it('rejects notes beyond the 20k character limit', function (): void {
    $template = Template::factory()->create();

    $this->actingAs(($this->adminUser)())
        ->put(route('admin.templates.additional-notes.update', $template), [
            'notes' => str_repeat('a', 20001),
        ])
        ->assertSessionHasErrors('notes');

    expect($template->refresh()->additional_site_notes)->toBeNull();
});

it('does not let a non-admin edit the additional site notes', function (): void {
    $template = Template::factory()->create();

    // Web form writes are rejected with a redirect + error flash rather than a
    // bare 403 (see ExceptionRenderer::permissionDenied).
    $this->actingAs(User::factory()->create()->assignRole(Role::MANAGER->value))
        ->put(route('admin.templates.additional-notes.update', $template), ['notes' => 'Nope'])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($template->refresh()->additional_site_notes)->toBeNull();
});

it('attaches uploaded images to the additional notes collection', function (): void {
    Storage::fake('private');

    $template = Template::factory()->create();

    $this->actingAs(($this->adminUser)())
        ->post(route('admin.templates.additional-notes.images.store', $template), [
            'images' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.png'),
            ],
        ])
        ->assertSessionHasNoErrors();

    expect($template->refresh()->getMedia(Template::ADDITIONAL_NOTES_COLLECTION))->toHaveCount(2);
});

it('refuses uploads past the image limit', function (): void {
    Storage::fake('private');

    $template = Template::factory()->create();

    foreach (range(1, Template::ADDITIONAL_NOTES_IMAGE_LIMIT) as $index) {
        $template
            ->addMedia(UploadedFile::fake()->image("note-{$index}.jpg"))
            ->toMediaCollection(Template::ADDITIONAL_NOTES_COLLECTION);
    }

    $this->actingAs(($this->adminUser)())
        ->post(route('admin.templates.additional-notes.images.store', $template), [
            'images' => [UploadedFile::fake()->image('overflow.jpg')],
        ])
        ->assertSessionHasErrors('images');

    expect($template->refresh()->getMedia(Template::ADDITIONAL_NOTES_COLLECTION))
        ->toHaveCount(Template::ADDITIONAL_NOTES_IMAGE_LIMIT);
});

it('removes an attached image', function (): void {
    Storage::fake('private');

    $template = Template::factory()->create();
    $media = $template
        ->addMedia(UploadedFile::fake()->image('note.jpg'))
        ->toMediaCollection(Template::ADDITIONAL_NOTES_COLLECTION);

    $this->actingAs(($this->adminUser)())
        ->delete(route('admin.templates.additional-notes.images.destroy', [$template, $media]))
        ->assertSessionHasNoErrors();

    expect($template->refresh()->getMedia(Template::ADDITIONAL_NOTES_COLLECTION))->toBeEmpty()
        ->and(Media::query()->whereKey($media->id)->exists())->toBeFalse();
});

it('will not serve or delete an image belonging to another template', function (): void {
    Storage::fake('private');

    $template = Template::factory()->create();
    $other = Template::factory()->create();
    $media = $other
        ->addMedia(UploadedFile::fake()->image('other.jpg'))
        ->toMediaCollection(Template::ADDITIONAL_NOTES_COLLECTION);

    $admin = ($this->adminUser)();

    $this->actingAs($admin)
        ->get(route('admin.templates.additional-notes.images.show', [$template, $media]))
        ->assertNotFound();

    $this->actingAs($admin)
        ->delete(route('admin.templates.additional-notes.images.destroy', [$template, $media]))
        ->assertNotFound();

    expect(Media::query()->whereKey($media->id)->exists())->toBeTrue();
});

it('copies the notes text and images when a template is duplicated', function (): void {
    Storage::fake('private');

    $original = Template::factory()->create([
        'additional_site_notes' => 'Standard site notes for this market.',
    ]);
    $original
        ->addMedia(UploadedFile::fake()->image('diagram.jpg'))
        ->toMediaCollection(Template::ADDITIONAL_NOTES_COLLECTION);

    $copy = app(TemplateCloningService::class)->duplicate($original);

    expect($copy->additional_site_notes)->toBe('Standard site notes for this market.')
        ->and($copy->getMedia(Template::ADDITIONAL_NOTES_COLLECTION))->toHaveCount(1)
        ->and($original->getMedia(Template::ADDITIONAL_NOTES_COLLECTION))->toHaveCount(1);
});

it('allows a field supervisor to view additional note images of an active template', function (): void {
    Storage::fake('private');

    $template = Template::factory()->create(['is_active' => true]);
    $media = $template
        ->addMedia(UploadedFile::fake()->image('note-drawing.jpg'))
        ->toMediaCollection(Template::ADDITIONAL_NOTES_COLLECTION);

    $tech = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->actingAs($tech)
        ->get(route('templates.additional-notes.images.show', [$template, $media]))
        ->assertOk();
});
