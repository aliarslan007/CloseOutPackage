<?php

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Crew\Models\CrewAssignment;
use App\Domains\Markets\Models\Market;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Models\Template;
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

it('shows role-scoped dashboard stats for admins', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();
    Project::factory()->count(2)->create(['market_id' => $market->id]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('stats.totalProjects', 2));
});

it('scopes dashboard stats to the assigned manager', function (): void {
    $this->withoutVite();

    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $otherManager = User::factory()->create()->assignRole(Role::MANAGER->value);

    Project::factory()->count(3)->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
    Project::factory()->count(5)->create(['market_id' => $market->id, 'manager_id' => $otherManager->id]);

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('stats.totalProjects', 3));
});

it('hides the activity feed from field supervisors', function (): void {
    $this->withoutVite();

    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create();
    $project->fieldSupervisors()->attach($field->id);

    $this->actingAs($field)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activities', null)
            ->has('stats'));
});

it('does not let managers dump all activities', function (): void {
    $this->withoutVite();

    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);

    foreach (range(1, 5) as $i) {
        activity()->causedBy($manager)->log('Test activity '.$i);
    }

    $this->actingAs($manager)
        ->get(route('dashboard').'?activities_all=1')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.totalProjects', 0)
            ->missing('activities.data.10'));

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('activities'));
});

it('scopes the manager activity feed to their own and their supervisors activity', function (): void {
    $this->withoutVite();

    $marketA = Market::factory()->create();
    $managerA = User::factory()->create()->assignRole(Role::MANAGER->value);
    $managerA->markets()->attach($marketA->id);
    $projectA = Project::factory()->create(['market_id' => $marketA->id, 'manager_id' => $managerA->id]);
    $supervisorA = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $projectA->fieldSupervisors()->attach($supervisorA->id);

    $marketB = Market::factory()->create();
    $managerB = User::factory()->create()->assignRole(Role::MANAGER->value);
    $managerB->markets()->attach($marketB->id);
    $projectB = Project::factory()->create(['market_id' => $marketB->id, 'manager_id' => $managerB->id]);
    $supervisorB = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $projectB->fieldSupervisors()->attach($supervisorB->id);

    activity()->causedBy($managerA)->log('Manager A action');
    activity()->causedBy($supervisorA)->log('Supervisor A action');
    activity()->causedBy($managerB)->log('Manager B action');
    activity()->causedBy($supervisorB)->log('Supervisor B action');

    $this->actingAs($managerA)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('activities.data', 2)
            ->where('activities.data.0.description', 'Manager A action')
            ->where('activities.data.1.description', 'Supervisor A action'));
});

it('lets admins dump all activities', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    foreach (range(1, 5) as $i) {
        activity()->log('Test activity '.$i);
    }

    $this->actingAs($admin)
        ->get(route('dashboard').'?activities_all=1')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('activities.data', 5));
});

it('scopes dashboard stats to assigned field supervisors', function (): void {
    $this->withoutVite();

    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $assigned = Project::factory()->create();
    Project::factory()->create();

    $assigned->fieldSupervisors()->attach($field->id);

    $this->actingAs($field)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('stats.totalProjects', 1));
});

it('exposes the visibility snapshot to managers', function (): void {
    $this->withoutVite();

    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    Project::factory()->count(2)->create(['market_id' => $market->id, 'manager_id' => $manager->id]);

    $this->actingAs($manager)
        ->get(route('manager.visibility'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Manager/Visibility')
            ->has('snapshot.projects', 2)
            ->where('snapshot.templateCount', 0));
});

it('denies the visibility snapshot to field supervisors', function (): void {
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->actingAs($field)
        ->get(route('manager.visibility'))
        ->assertForbidden();
});

it('assigns a crew member as a manager', function (): void {
    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->actingAs($manager)
        ->post(route('projects.crew.store', $project), [
            'user_id' => $field->id,
            'stage' => 'construction',
            'role_description' => 'Lead Tech',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('crew_assignments', [
        'project_id' => $project->id,
        'user_id' => $field->id,
        'stage' => 'construction',
        'role_description' => 'Lead Tech',
    ]);
});

it('rejects assigning a non-field-supervisor to the crew', function (): void {
    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
    $other = User::factory()->create()->assignRole(Role::MANAGER->value);

    $this->actingAs($manager)
        ->post(route('projects.crew.store', $project), ['user_id' => $other->id])
        ->assertSessionHasErrors('user_id');
});

it('removes a crew member', function (): void {
    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    CrewAssignment::factory()->create([
        'project_id' => $project->id,
        'user_id' => $field->id,
    ]);

    $this->actingAs($manager)
        ->delete(route('projects.crew.destroy', [$project, $field]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(CrewAssignment::count())->toBe(0);
});

it('denies crew management to field supervisors', function (): void {
    $project = Project::factory()->create();
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->actingAs($field)
        ->post(route('projects.crew.store', $project), ['user_id' => $field->id])
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');
});

it('lists soft-deleted packages for admins', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $package = CloseoutPackage::factory()->create();
    $package->delete();

    $this->actingAs($admin)
        ->get(route('admin.packages.deleted'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Packages/Deleted')
            ->has('packages.data', 1));
});

it('records who deleted a package and lists it with full context', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $creator = User::factory()->create()->assignRole(Role::MANAGER->value);
    $market = Market::factory()->create(['name' => 'Ohio Market']);
    $project = Project::factory()->create(['market_id' => $market->id]);
    $template = Template::factory()->create(['market_id' => $market->id]);
    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
        'created_by' => $creator->id,
        'status' => 'submitted',
    ]);

    $this->actingAs($admin)
        ->delete(route('packages.destroy', $package))
        ->assertRedirect();

    $trashed = CloseoutPackage::withTrashed()->findOrFail($package->id);

    expect($trashed->deleted_by)->toBe($admin->id)
        ->and($trashed->deleted_at)->not->toBeNull()
        ->and($trashed->status)->toBe(PackageStatus::SUBMITTED);

    $this->actingAs($admin)
        ->get(route('admin.packages.deleted'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Packages/Deleted')
            ->has('packages.data', 1, fn (Assert $item) => $item
                ->where('deleted_by', $admin->id)
                ->where('deleted_at', $trashed->deleted_at->toISOString())
                ->where('status', 'submitted')
                ->where('created_by', $creator->id)
                ->where('project.market.name', 'Ohio Market')
                ->etc()));
});

it('restores a soft-deleted package as admin', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $package = CloseoutPackage::factory()->create(['status' => 'under_review']);
    $package->delete();

    $this->actingAs($admin)
        ->post(route('admin.packages.restore', $package))
        ->assertRedirect(route('admin.packages.deleted'))
        ->assertSessionHas('success');

    $restored = CloseoutPackage::withTrashed()->findOrFail($package->id);

    expect($restored->trashed())->toBeFalse()
        ->and($restored->deleted_by)->toBeNull()
        ->and($restored->status)->toBe(PackageStatus::UNDER_REVIEW);
});

it('rejects restoring a package that is not soft-deleted', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $package = CloseoutPackage::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.packages.restore', $package))
        ->assertRedirect()
        ->assertSessionHas('error', 'Only soft-deleted packages can be restored.');

    expect(CloseoutPackage::find($package->id))->not->toBeNull();
});

it('denies restoring to non-admins', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $package = CloseoutPackage::factory()->create();
    $package->delete();

    $this->actingAs($manager)
        ->post(route('admin.packages.restore', $package))
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');
});

it('purges a soft-deleted package and its photo media as admin', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();
    $project = Project::factory()->create(['market_id' => $market->id]);
    $template = Template::factory()->create(['market_id' => $market->id]);
    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
    ]);
    $photo = Photo::factory()->create(['closeout_package_id' => $package->id]);
    $photo->addMedia(UploadedFile::fake()->image('site.jpg'))->toMediaCollection(Photo::COLLECTION);

    $package->delete();

    $this->actingAs($admin)
        ->delete(route('admin.packages.purge', $package))
        ->assertRedirect(route('admin.packages.deleted'))
        ->assertSessionHas('success');

    expect(CloseoutPackage::withTrashed()->find($package->id))->toBeNull()
        ->and(Photo::find($photo->id))->toBeNull();
});

it('rejects purging a package that is not soft-deleted', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $package = CloseoutPackage::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.packages.purge', $package))
        ->assertRedirect()
        ->assertSessionHas('error', 'Only soft-deleted packages can be purged.');

    expect(CloseoutPackage::find($package->id))->not->toBeNull();
});

it('denies purging to non-admins', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $package = CloseoutPackage::factory()->create();
    $package->delete();

    $this->actingAs($manager)
        ->delete(route('admin.packages.purge', $package))
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');
});
