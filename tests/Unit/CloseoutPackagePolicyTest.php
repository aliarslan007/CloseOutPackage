<?php

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Policies\CloseoutPackagePolicy;
use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->policy = fn (): CloseoutPackagePolicy => app(CloseoutPackagePolicy::class);

    $this->fixture = function (): array {
        $market = Market::factory()->create();
        $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
        $otherManager = User::factory()->create()->assignRole(Role::MANAGER->value);
        $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
        $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
        $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
        $project = Project::factory()->create([
            'market_id' => $market->id,
            'manager_id' => $manager->id,
        ]);
        $project->fieldSupervisors()->attach($supervisor->id);

        $package = CloseoutPackage::factory()->create([
            'project_id' => $project->id,
            'created_by' => $supervisor->id,
            'status' => PackageStatus::APPROVED->value,
        ]);

        return compact(
            'market',
            'manager',
            'otherManager',
            'supervisor',
            'admin',
            'customer',
            'project',
            'package',
        );
    };

    $this->locked = function (array $fixture): CloseoutPackage {
        $fixture['package']->update([
            'locked_at' => now(),
            'locked_by' => $fixture['manager']->id,
        ]);

        return $fixture['package']->fresh();
    };
});

it('lets an admin lock an approved package', function (): void {
    $fixture = ($this->fixture)();

    expect(($this->policy)()->lock($fixture['admin'], $fixture['package']))->toBeTrue();
});

it('lets the assigned manager lock an approved package', function (): void {
    $fixture = ($this->fixture)();

    expect(($this->policy)()->lock($fixture['manager'], $fixture['package']))->toBeTrue();
});

it('denies a non-assigned manager from locking', function (): void {
    $fixture = ($this->fixture)();

    expect(($this->policy)()->lock($fixture['otherManager'], $fixture['package']))->toBeFalse();
});

it('denies a field supervisor from locking', function (): void {
    $fixture = ($this->fixture)();

    expect(($this->policy)()->lock($fixture['supervisor'], $fixture['package']))->toBeFalse();
});

it('denies a customer from locking', function (): void {
    $fixture = ($this->fixture)();

    expect(($this->policy)()->lock($fixture['customer'], $fixture['package']))->toBeFalse();
});

it('denies locking a package that is not approved', function (): void {
    $fixture = ($this->fixture)();
    $fixture['package']->update(['status' => PackageStatus::SUBMITTED]);

    $policy = ($this->policy)();

    expect($policy->lock($fixture['manager'], $fixture['package']))->toBeFalse()
        ->and($policy->lock($fixture['admin'], $fixture['package']))->toBeFalse();
});

it('lets the locker unlock the package', function (): void {
    $fixture = ($this->fixture)();
    $package = ($this->locked)($fixture);

    expect(($this->policy)()->unlock($fixture['manager'], $package))->toBeTrue();
});

it('lets an admin unlock the package', function (): void {
    $fixture = ($this->fixture)();
    $package = ($this->locked)($fixture);

    expect(($this->policy)()->unlock($fixture['admin'], $package))->toBeTrue();
});

it('denies another manager from unlocking the package', function (): void {
    $fixture = ($this->fixture)();
    $package = ($this->locked)($fixture);

    expect(($this->policy)()->unlock($fixture['otherManager'], $package))->toBeFalse();
});

it('denies a non-locker field supervisor from viewing a locked package', function (): void {
    $fixture = ($this->fixture)();
    $package = ($this->locked)($fixture);

    expect(($this->policy)()->view($fixture['supervisor'], $package))->toBeFalse();
});

it('denies a non-locker manager from viewing a locked package', function (): void {
    $fixture = ($this->fixture)();
    $package = ($this->locked)($fixture);

    expect(($this->policy)()->view($fixture['otherManager'], $package))->toBeFalse();
});

it('lets the locker view a locked package', function (): void {
    $fixture = ($this->fixture)();
    $package = ($this->locked)($fixture);

    expect(($this->policy)()->view($fixture['manager'], $package))->toBeTrue();
});

it('lets an admin view a locked package', function (): void {
    $fixture = ($this->fixture)();
    $package = ($this->locked)($fixture);

    expect(($this->policy)()->view($fixture['admin'], $package))->toBeTrue();
});

it('denies a customer access to a locked package even with portal access', function (): void {
    $fixture = ($this->fixture)();
    CustomerProjectAccess::factory()->create([
        'user_id' => $fixture['customer']->id,
        'project_id' => $fixture['project']->id,
    ]);

    $policy = ($this->policy)();

    expect($policy->view($fixture['customer'], $fixture['package']))->toBeTrue();

    $package = ($this->locked)($fixture);

    expect($policy->view($fixture['customer'], $package))->toBeFalse();
});

it('denies deleting a locked package to any non-admin', function (): void {
    $fixture = ($this->fixture)();
    $package = ($this->locked)($fixture);
    $policy = ($this->policy)();

    expect($policy->delete($fixture['manager'], $package))->toBeFalse()
        ->and($policy->delete($fixture['supervisor'], $package))->toBeFalse()
        ->and($policy->delete($fixture['otherManager'], $package))->toBeFalse();
});

it('lets an admin delete a locked package', function (): void {
    $fixture = ($this->fixture)();
    $package = ($this->locked)($fixture);

    expect(($this->policy)()->delete($fixture['admin'], $package))->toBeTrue();
});

it('lets the assigned manager edit a package in draft status', function (): void {
    $fixture = ($this->fixture)();
    $package = $fixture['package'];
    $package->update(['status' => PackageStatus::DRAFT->value]);

    $policy = ($this->policy)();

    expect($policy->update($fixture['manager'], $package))->toBeTrue()
        ->and($policy->saveDraft($fixture['manager'], $package))->toBeTrue()
        ->and($policy->submitStage($fixture['manager'], $package))->toBeTrue()
        ->and($policy->submitPackage($fixture['manager'], $package))->toBeTrue();
});

it('denies a manager from editing a submitted package', function (): void {
    $fixture = ($this->fixture)();
    $package = $fixture['package'];
    $package->update(['status' => PackageStatus::SUBMITTED->value]);

    $policy = ($this->policy)();

    expect($policy->update($fixture['manager'], $package))->toBeFalse()
        ->and($policy->saveDraft($fixture['manager'], $package))->toBeFalse();
});
