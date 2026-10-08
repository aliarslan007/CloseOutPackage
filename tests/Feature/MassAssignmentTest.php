<?php

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Markets\Models\Market;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Storage::fake('private');
    // User create/update dispatches an SSO sync job; irrelevant here.
    Queue::fake();
});

it('cannot mass-assign uuid when creating a user', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $attackerUuid = '11111111-1111-4111-8111-111111111111';

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'first_name' => 'John',
            'last_name' => 'Field',
            'email' => 'john@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'role' => Role::FIELD_SUPERVISOR->value,
            'uuid' => $attackerUuid,
        ])->assertRedirect();

    $user = User::where('email', 'john@example.com')->firstOrFail();

    expect($user->uuid)->not->toBe($attackerUuid)
        ->and($user->uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
});

it('cannot mass-assign uuid when updating a user', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $user = User::factory()->create(['uuid' => '22222222-2222-4222-8222-222222222222'])
        ->assignRole(Role::FIELD_SUPERVISOR->value);
    $attackerUuid = '33333333-3333-4333-8333-333333333333';

    $this->actingAs($admin)
        ->put(route('admin.users.update', $user), [
            'first_name' => 'John',
            'last_name' => 'Field',
            'email' => $user->email,
            'role' => Role::FIELD_SUPERVISOR->value,
            'uuid' => $attackerUuid,
        ])->assertRedirect();

    expect($user->fresh()->uuid)->not->toBe($attackerUuid);
});

it('cannot mass-assign slug or created_by when creating a market', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    $this->actingAs($admin)
        ->post(route('admin.markets.store'), [
            'name' => 'Ohio Market',
            'slug' => 'attacker-slug',
            'created_by' => 999,
        ])->assertRedirect();

    $market = Market::where('name', 'Ohio Market')->firstOrFail();

    expect($market->slug)->not->toBe('attacker-slug')
        ->and($market->created_by)->toBe($admin->id);
});

it('cannot mass-assign package status or approver fields when creating a package', function (): void {
    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
    $template = Template::factory()->create(['market_id' => $market->id]);

    $this->actingAs($manager)
        ->post(route('packages.store', $project), [
            'project_id' => $project->id,
            'template_id' => $template->id,
            'status' => 'approved',
            'approved_by' => 999,
            'deleted_by' => 999,
            'revision_major' => 99,
        ])->assertRedirect();

    $package = CloseoutPackage::firstOrFail();

    expect($package->status)->toBe(PackageStatus::DRAFT)
        ->and($package->approved_by)->toBeNull()
        ->and($package->deleted_by)->toBeNull()
        ->and($package->revision_major)->toBe(1);
});

it('cannot mass-assign uploaded_by or approval fields when uploading a photo', function (): void {
    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
    $template = Template::factory()->create(['market_id' => $market->id]);
    $section = $template->sections()->firstOrFail();
    $category = TemplatePictureCategory::factory()->create(['template_section_id' => $section->id]);

    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $field->markets()->attach($market->id);
    $project->fieldSupervisors()->attach($field->id);

    $this->actingAs($manager)
        ->post(route('packages.store', $project), [
            'project_id' => $project->id,
            'template_id' => $template->id,
        ]);

    $package = CloseoutPackage::firstOrFail();
    $stage = $package->stages()->where('stage', StageType::PRE_CONSTRUCTION->value)->firstOrFail();
    $selection = $stage->categorySelections()->firstOrFail();

    $this->actingAs($field)
        ->post(route('photos.store', [$package, StageType::PRE_CONSTRUCTION->value]), [
            'photo' => UploadedFile::fake()->image('site.jpg'),
            'category_selection_id' => $selection->id,
            'position' => 1,
            'uploaded_by' => $manager->id,
            'is_approved' => true,
            'status' => 'approved',
            'approved_by' => 999,
        ])->assertRedirect();

    $photo = Photo::firstOrFail();

    expect($photo->uploaded_by)->toBe($field->id)
        ->and($photo->is_approved)->toBeNull()
        ->and($photo->status)->toBe(PhotoStatus::PENDING)
        ->and($photo->approved_by)->toBeNull();
});
