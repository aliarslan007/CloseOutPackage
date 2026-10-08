<?php

declare(strict_types=1);

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\Photos\Enums\PhotoStatus;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Enums\StageType;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplatePictureCategory;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    Storage::fake('public');

    $adminRole = SpatieRole::firstOrCreate(['name' => Role::ADMIN->value]);
    $managerRole = SpatieRole::firstOrCreate(['name' => Role::MANAGER->value]);
    $fieldRole = SpatieRole::firstOrCreate(['name' => Role::FIELD_SUPERVISOR->value]);

    $admin = User::factory()->create();
    $admin->assignRole($adminRole);

    $manager = User::factory()->create();
    $manager->assignRole($managerRole);

    $field = User::factory()->create();
    $field->assignRole($fieldRole);

    $unassigned = User::factory()->create();
    $unassigned->assignRole($fieldRole);

    $project = Project::factory()->create([
        'manager_id' => $manager->id,
    ]);
    $project->fieldSupervisors()->attach($field->id);

    $template = Template::factory()->create();
    $section = $template->sections()->where('stage', StageType::PRE_CONSTRUCTION->value)->firstOrFail();
    $category = TemplatePictureCategory::factory()->create([
        'template_section_id' => $section->id,
        'default_picture_count' => 5,
    ]);

    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
        'status' => PackageStatus::DRAFT,
    ]);

    $stage = PackageStage::factory()->create([
        'closeout_package_id' => $package->id,
        'stage' => StageType::PRE_CONSTRUCTION->value,
    ]);

    $selection = PackageCategorySelection::factory()->create([
        'package_stage_id' => $stage->id,
        'template_picture_category_id' => $category->id,
        'picture_count' => 5,
    ]);

    $photo = Photo::factory()->create([
        'closeout_package_id' => $package->id,
        'package_stage_id' => $stage->id,
        'package_category_selection_id' => $selection->id,
        'uploaded_by' => $field->id,
        'position' => 1,
        'status' => PhotoStatus::PENDING->value,
        'notes' => null,
    ]);

    $this->setupData = compact('admin', 'manager', 'field', 'unassigned', 'project', 'package', 'photo');
});

it('allows an assigned field supervisor to add a note to a photo in a draft package', function (): void {
    ['field' => $field, 'package' => $package, 'photo' => $photo] = $this->setupData;

    $this->actingAs($field)
        ->patch(route('photos.notes.update', [$package, $photo]), [
            'notes' => 'Grounding cable connected to busbar with 2-hole lug',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Photo note saved.');

    expect($photo->refresh()->notes)->toBe('Grounding cable connected to busbar with 2-hole lug');
});

it('allows modifying an existing photo note', function (): void {
    ['field' => $field, 'package' => $package, 'photo' => $photo] = $this->setupData;

    $photo->update(['notes' => 'Old note']);

    $this->actingAs($field)
        ->patch(route('photos.notes.update', [$package, $photo]), [
            'notes' => 'Updated note with verified torque values',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Photo note saved.');

    expect($photo->refresh()->notes)->toBe('Updated note with verified torque values');
});

it('allows removing a photo note by submitting null or empty text', function (): void {
    ['field' => $field, 'package' => $package, 'photo' => $photo] = $this->setupData;

    $photo->update(['notes' => 'Note to be removed']);

    $this->actingAs($field)
        ->patch(route('photos.notes.update', [$package, $photo]), [
            'notes' => '   ',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Photo note removed.');

    expect($photo->refresh()->notes)->toBeNull();
});

it('validates that photo notes cannot exceed 500 characters', function (): void {
    ['field' => $field, 'package' => $package, 'photo' => $photo] = $this->setupData;

    $tooLongNote = str_repeat('a', 501);

    $this->actingAs($field)
        ->patch(route('photos.notes.update', [$package, $photo]), [
            'notes' => $tooLongNote,
        ])
        ->assertSessionHasErrors(['notes']);

    expect($photo->refresh()->notes)->toBeNull();
});

it('blocks updating photo notes when the package has been submitted', function (): void {
    ['field' => $field, 'package' => $package, 'photo' => $photo] = $this->setupData;

    $package->update(['status' => PackageStatus::SUBMITTED]);

    $this->actingAs($field)
        ->patch(route('photos.notes.update', [$package, $photo]), [
            'notes' => 'Attempted note after submit',
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');

    expect($photo->refresh()->notes)->toBeNull();
});

it('allows an administrator to update notes', function (): void {
    ['admin' => $admin, 'package' => $package, 'photo' => $photo] = $this->setupData;

    $this->actingAs($admin)
        ->patch(route('photos.notes.update', [$package, $photo]), [
            'notes' => 'Admin verified equipment serial number #49102',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Photo note saved.');

    expect($photo->refresh()->notes)->toBe('Admin verified equipment serial number #49102');
});

it('forbids an unassigned field supervisor from modifying notes', function (): void {
    ['unassigned' => $unassigned, 'package' => $package, 'photo' => $photo] = $this->setupData;

    $this->actingAs($unassigned)
        ->patch(route('photos.notes.update', [$package, $photo]), [
            'notes' => 'Unauthorized note attempt',
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');

    expect($photo->refresh()->notes)->toBeNull();
});

it('allows the assigned manager to update notes when package is submitted for review', function (): void {
    ['manager' => $manager, 'package' => $package, 'photo' => $photo] = $this->setupData;

    $package->update(['status' => PackageStatus::SUBMITTED]);

    $this->actingAs($manager)
        ->patch(route('photos.notes.update', [$package, $photo]), [
            'notes' => 'Manager review note on submitted package',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Photo note saved.');

    expect($photo->refresh()->notes)->toBe('Manager review note on submitted package');
});

it('allows the assigned manager to update notes when package is in draft', function (): void {
    ['manager' => $manager, 'package' => $package, 'photo' => $photo] = $this->setupData;

    // Package is in DRAFT status
    $this->actingAs($manager)
        ->patch(route('photos.notes.update', [$package, $photo]), [
            'notes' => 'Manager note in draft',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Photo note saved.');

    expect($photo->refresh()->notes)->toBe('Manager note in draft');
});

it('denies an unassigned manager from updating notes on a submitted package', function (): void {
    ['package' => $package, 'photo' => $photo] = $this->setupData;

    $package->update(['status' => PackageStatus::SUBMITTED]);
    $otherManager = User::factory()->create()->assignRole(Role::MANAGER->value);

    $this->actingAs($otherManager)
        ->patch(route('photos.notes.update', [$package, $photo]), [
            'notes' => 'Unassigned manager note',
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');

    expect($photo->refresh()->notes)->toBeNull();
});

it('allows the assigned manager to update notes when package is under review', function (): void {
    ['manager' => $manager, 'package' => $package, 'photo' => $photo] = $this->setupData;

    $package->update(['status' => PackageStatus::UNDER_REVIEW]);

    $this->actingAs($manager)
        ->patch(route('photos.notes.update', [$package, $photo]), [
            'notes' => 'Manager review note while actively under review',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Photo note saved.');

    expect($photo->refresh()->notes)->toBe('Manager review note while actively under review');
});

it('exposes photo notes on the package page while the technician is reviewing a submitted package', function (): void {
    $this->withoutVite();

    ['field' => $field, 'package' => $package, 'photo' => $photo] = $this->setupData;

    $photo->update(['notes' => 'Grounding cable landed on the busbar']);
    $package->update(['status' => PackageStatus::SUBMITTED]);

    $this->actingAs($field)
        ->get(route('packages.show', $package))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Packages/Show')
            ->where('package.photos.0.notes', 'Grounding cable landed on the busbar'));
});

it('exposes photo notes on the package page after final approval', function (): void {
    $this->withoutVite();

    ['field' => $field, 'package' => $package, 'photo' => $photo] = $this->setupData;

    $photo->update(['notes' => 'Torque verified at 45 ft-lbs', 'is_approved' => true]);
    $package->update(['status' => PackageStatus::APPROVED]);

    $this->actingAs($field)
        ->get(route('packages.show', $package))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Packages/Show')
            ->where('package.photos.0.notes', 'Torque verified at 45 ft-lbs'));
});
