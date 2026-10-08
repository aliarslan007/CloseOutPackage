<?php

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $this->project = Project::factory()->create();
    // CloseoutPackagePolicy grants a field supervisor access via project assignment.
    $this->project->fieldSupervisors()->attach($this->supervisor->id, ['assigned_by' => null]);
    $this->package = CloseoutPackage::factory()->create([
        'project_id' => $this->project->id,
        'status' => PackageStatus::DRAFT->value,
    ]);
});

it('sends no primary technician until a manager stars one', function (): void {
    $this->actingAs($this->supervisor)
        ->get(route('packages.show', $this->package))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Packages/Show')
            ->where('primary_technician', null));
});

it('sends the starred primary technician on the close-out package page', function (): void {
    $technician = User::factory()->create([
        'name' => 'Nico John',
        'email' => 'fallinsupervisor@mail.com',
    ]);

    $this->project->update(['primary_supervisor_id' => $technician->id]);

    $this->actingAs($this->supervisor)
        ->get(route('packages.show', $this->package))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Packages/Show')
            ->where('primary_technician.id', $technician->id)
            ->where('primary_technician.name', 'Nico John')
            ->where('primary_technician.email', 'fallinsupervisor@mail.com')
            ->has('primary_technician.profile_photo_url'));
});

it('sends the primary technician on the review page too', function (): void {
    $technician = User::factory()->create(['name' => 'Helli Barger']);
    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);

    $this->project->update(['primary_supervisor_id' => $technician->id]);
    $this->package->update(['status' => PackageStatus::CUSTOMER_REVIEW->value]);

    // The review render is the view-but-cannot-update branch, which is the
    // customer's — a field supervisor with update rights gets the build page.
    CustomerProjectAccess::factory()->create([
        'user_id' => $customer->id,
        'project_id' => $this->project->id,
    ]);

    $this->actingAs($customer)
        ->get(route('packages.show', $this->package))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Packages/Review')
            ->where('primary_technician.name', 'Helli Barger'));
});
