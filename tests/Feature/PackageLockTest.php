<?php

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Models\Template;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Storage::fake('private');

    $this->fixture = function (): array {
        $market = Market::factory()->create();
        $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
        $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
        $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
        $project = Project::factory()->create([
            'market_id' => $market->id,
            'manager_id' => $manager->id,
        ]);
        $project->fieldSupervisors()->attach($supervisor->id);
        $template = Template::factory()->create(['market_id' => $market->id]);

        $package = CloseoutPackage::factory()->create([
            'project_id' => $project->id,
            'template_id' => $template->id,
            'created_by' => $supervisor->id,
            'status' => PackageStatus::APPROVED->value,
        ]);

        return compact('market', 'manager', 'supervisor', 'admin', 'project', 'template', 'package');
    };

    $this->lock = function (CloseoutPackage $package, User $locker): CloseoutPackage {
        $package->update(['locked_at' => now(), 'locked_by' => $locker->id]);

        return $package;
    };
});

it('locks an approved package as the assigned project manager', function (): void {
    $fixture = ($this->fixture)();

    $this->actingAs($fixture['manager'])
        ->post(route('packages.lock', $fixture['package']))
        ->assertRedirect()
        ->assertSessionHas('success', 'Package locked.');

    $package = $fixture['package']->fresh();

    expect($package->isLocked())->toBeTrue()
        ->and($package->locked_by)->toBe($fixture['manager']->id);
});

it('locks an approved package as an admin', function (): void {
    $fixture = ($this->fixture)();

    $this->actingAs($fixture['admin'])
        ->post(route('packages.lock', $fixture['package']))
        ->assertRedirect()
        ->assertSessionHas('success', 'Package locked.');

    expect($fixture['package']->fresh()->isLocked())->toBeTrue();
});

it('rejects locking a non-approved package', function (): void {
    $fixture = ($this->fixture)();
    $fixture['package']->update(['status' => PackageStatus::SUBMITTED]);

    $this->actingAs($fixture['manager'])
        ->post(route('packages.lock', $fixture['package']))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($fixture['package']->fresh()->isLocked())->toBeFalse();
});

it('rejects locking an already locked package', function (): void {
    $fixture = ($this->fixture)();
    ($this->lock)($fixture['package'], $fixture['manager']);

    $this->actingAs($fixture['manager'])
        ->post(route('packages.lock', $fixture['package']))
        ->assertRedirect()
        ->assertSessionHas('error', 'This package is already locked.');
});

it('rejects locking a package by a field supervisor', function (): void {
    $fixture = ($this->fixture)();

    $this->actingAs($fixture['supervisor'])
        ->post(route('packages.lock', $fixture['package']))
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');

    expect($fixture['package']->fresh()->isLocked())->toBeFalse();
});

it('unlocks as the user who locked the package', function (): void {
    $fixture = ($this->fixture)();
    ($this->lock)($fixture['package'], $fixture['manager']);

    $this->actingAs($fixture['manager'])
        ->post(route('packages.unlock', $fixture['package']))
        ->assertRedirect()
        ->assertSessionHas('success', 'Package unlocked.');

    $package = $fixture['package']->fresh();

    expect($package->isLocked())->toBeFalse()
        ->and($package->locked_by)->toBeNull();
});

it('unlocks as an admin', function (): void {
    $fixture = ($this->fixture)();
    ($this->lock)($fixture['package'], $fixture['manager']);

    $this->actingAs($fixture['admin'])
        ->post(route('packages.unlock', $fixture['package']))
        ->assertRedirect()
        ->assertSessionHas('success', 'Package unlocked.');

    expect($fixture['package']->fresh()->isLocked())->toBeFalse();
});

it('rejects unlocking by a manager who did not lock the package', function (): void {
    $fixture = ($this->fixture)();
    ($this->lock)($fixture['package'], $fixture['manager']);
    $otherManager = User::factory()->create()->assignRole(Role::MANAGER->value);

    $this->actingAs($otherManager)
        ->post(route('packages.unlock', $fixture['package']))
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');

    expect($fixture['package']->fresh()->isLocked())->toBeTrue();
});

it('blocks a field supervisor from opening a locked package', function (): void {
    $fixture = ($this->fixture)();
    ($this->lock)($fixture['package'], $fixture['manager']);

    $this->actingAs($fixture['supervisor'])
        ->get(route('packages.show', $fixture['package']))
        ->assertForbidden();
});

it('allows the user who locked the package to open it', function (): void {
    $fixture = ($this->fixture)();
    ($this->lock)($fixture['package'], $fixture['manager']);

    $this->actingAs($fixture['manager'])
        ->get(route('packages.show', $fixture['package']))
        ->assertOk();
});

it('allows an admin to open a locked package', function (): void {
    $fixture = ($this->fixture)();
    ($this->lock)($fixture['package'], $fixture['manager']);

    $this->actingAs($fixture['admin'])
        ->get(route('packages.show', $fixture['package']))
        ->assertOk();
});

it('blocks a non-locker from downloading the package PDF', function (): void {
    $fixture = ($this->fixture)();
    ($this->lock)($fixture['package'], $fixture['manager']);

    $this->actingAs($fixture['supervisor'])
        ->get(route('packages.pdf.download', $fixture['package']))
        ->assertForbidden();
});

it('blocks a non-locker from emailing the final PDF', function (): void {
    $fixture = ($this->fixture)();
    ($this->lock)($fixture['package'], $fixture['manager']);
    $otherManager = User::factory()->create()->assignRole(Role::MANAGER->value);

    $this->actingAs($otherManager)
        ->post(route('manager.packages.pdf.email', $fixture['package']), [
            'email_to' => 'vzw@example.com',
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');
});

it('blocks a non-locker from emailing the progress PDF', function (): void {
    $fixture = ($this->fixture)();
    ($this->lock)($fixture['package'], $fixture['manager']);
    $otherManager = User::factory()->create()->assignRole(Role::MANAGER->value);

    $this->actingAs($otherManager)
        ->post(route('manager.packages.pdf.progress-email', $fixture['package']), [
            'email_to' => 'vzw@example.com',
            'closeout_package_id' => $fixture['package']->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');
});

it('returns 410 gone for a signed PDF link to a locked package', function (): void {
    $fixture = ($this->fixture)();
    ($this->lock)($fixture['package'], $fixture['manager']);

    $url = URL::signedRoute('pdf.signed', [
        'package' => $fixture['package']->id,
        'type' => 'final',
    ], now()->addDays(7));

    $this->get($url)->assertStatus(410);
});

it('hides a locked package from the customer portal open attempt', function (): void {
    $fixture = ($this->fixture)();
    ($this->lock)($fixture['package'], $fixture['manager']);
    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    CustomerProjectAccess::factory()->create([
        'user_id' => $customer->id,
        'project_id' => $fixture['project']->id,
    ]);

    $this->actingAs($customer)
        ->get(route('portal.packages.show', $fixture['package']))
        ->assertNotFound();
});

it('exposes the locker and timestamp on the project details package list', function (): void {
    $fixture = ($this->fixture)();
    ($this->lock)($fixture['package'], $fixture['manager']);

    $this->actingAs($fixture['manager'])
        ->get(route('projects.show', $fixture['project']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Show')
            ->has('project.closeout_packages', 1)
            ->where('project.closeout_packages.0.locked', true)
            ->where('project.closeout_packages.0.locked_by.id', $fixture['manager']->id)
            ->where('project.closeout_packages.0.locked_by.name', $fixture['manager']->name)
            ->where('project.closeout_packages.0.locked_at', fn (mixed $value): bool => is_string($value) && $value !== ''));
});

it('keeps a locked package in the customer portal package list', function (): void {
    $fixture = ($this->fixture)();
    ($this->lock)($fixture['package'], $fixture['manager']);
    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    CustomerProjectAccess::factory()->create([
        'user_id' => $customer->id,
        'project_id' => $fixture['project']->id,
    ]);

    $this->actingAs($customer)
        ->get(route('portal.projects.show', $fixture['project']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customer/Portal/Projects/Show')
            ->has('project.packages', 1)
            ->where('project.packages.0.id', $fixture['package']->id)
            ->where('project.packages.0.locked', true)
            ->where('project.packages.0.locked_by.id', $fixture['manager']->id)
            ->where('project.packages.0.locked_by.name', $fixture['manager']->name)
            ->where('project.packages.0.locked_at', fn (mixed $value): bool => is_string($value) && $value !== ''));
});

it('blocks a field supervisor from deleting a locked package', function (): void {
    $fixture = ($this->fixture)();
    ($this->lock)($fixture['package'], $fixture['manager']);

    $this->actingAs($fixture['supervisor'])
        ->delete(route('packages.destroy', $fixture['package']))
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');

    $this->assertNotSoftDeleted('closeout_packages', ['id' => $fixture['package']->id]);
});

it('allows an admin to delete a locked package', function (): void {
    $fixture = ($this->fixture)();
    ($this->lock)($fixture['package'], $fixture['manager']);

    $this->actingAs($fixture['admin'])
        ->delete(route('packages.destroy', $fixture['package']))
        ->assertRedirect()
        ->assertSessionHas('success', 'Package deleted.');

    $this->assertSoftDeleted('closeout_packages', ['id' => $fixture['package']->id]);
});

it('lets the locker keep view access after being removed from the project', function (): void {
    $fixture = ($this->fixture)();
    ($this->lock)($fixture['package'], $fixture['manager']);
    $fixture['project']->update(['manager_id' => null]);

    $this->actingAs($fixture['manager'])
        ->get(route('packages.show', $fixture['package']))
        ->assertOk();
});
