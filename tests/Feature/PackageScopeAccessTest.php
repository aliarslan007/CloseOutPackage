<?php

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Models\Template;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Scope of work and site access notes are captured per package on the
 * pre-construction step, not on the project.
 */
beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->market = Market::factory()->create();
    $this->manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $this->manager->markets()->attach($this->market->id);
    $this->admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $this->technician = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->project = Project::factory()->create([
        'market_id' => $this->market->id,
        'manager_id' => $this->manager->id,
    ]);

    $this->template = Template::factory()->create(['market_id' => $this->market->id]);
    $this->package = CloseoutPackage::factory()->create([
        'project_id' => $this->project->id,
        'template_id' => $this->template->id,
        'created_by' => $this->technician->id,
        'status' => PackageStatus::DRAFT,
    ]);
});

it('lets the technician who created the package capture scope and site access', function (): void {
    $this->actingAs($this->technician)
        ->put(route('packages.site-info.update', $this->package), [
            'scope_of_work' => 'Replace 3 RRUs and re-terminate fiber at sector 2.',
            'site_access_notes' => 'Check in at the north gate, gate code #4921.',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $siteInfo = $this->package->siteInfo()->firstOrFail();

    expect($siteInfo->scope_of_work)->toBe('Replace 3 RRUs and re-terminate fiber at sector 2.')
        ->and($siteInfo->site_access_notes)->toBe('Check in at the north gate, gate code #4921.');
});

it('lets the assigned manager capture scope and site access', function (): void {
    $this->actingAs($this->manager)
        ->put(route('packages.site-info.update', $this->package), [
            'scope_of_work' => 'Install new cabinet pad.',
            'site_access_notes' => 'Keybox code: 8844.',
        ])
        ->assertSessionHasNoErrors();

    expect($this->package->siteInfo()->firstOrFail()->site_access_notes)->toBe('Keybox code: 8844.');
});

it('rejects scope and access notes over 2500 characters', function (): void {
    $this->actingAs($this->manager)
        ->put(route('packages.site-info.update', $this->package), [
            'scope_of_work' => str_repeat('a', 2501),
            'site_access_notes' => str_repeat('b', 2501),
        ])
        ->assertSessionHasErrors(['scope_of_work', 'site_access_notes']);

    expect($this->package->siteInfo()->first())->toBeNull();
});

it('stores a scope of work at the full 2500 characters', function (): void {
    // The column is a varchar, so the migration widening it is the thing that
    // makes this pass: the request limit alone would not store the value.
    $scope = str_repeat('a', 2500);

    $this->actingAs($this->manager)
        ->put(route('packages.site-info.update', $this->package), [
            'scope_of_work' => $scope,
        ])
        ->assertSessionHasNoErrors();

    expect($this->package->siteInfo()->firstOrFail()->scope_of_work)->toBe($scope);
});

it('deletes a stored value when its field is emptied', function (): void {
    $this->package->siteInfo()->create([
        'scope_of_work' => 'Old scope',
        'site_access_notes' => 'Old access notes',
    ]);

    $this->actingAs($this->manager)
        ->put(route('packages.site-info.update', $this->package), [
            'scope_of_work' => '',
            'site_access_notes' => '',
        ])
        ->assertSessionHasNoErrors();

    $siteInfo = $this->package->siteInfo()->firstOrFail();

    expect($siteInfo->scope_of_work)->toBe('')
        ->and($siteInfo->site_access_notes)->toBe('');
});

it('refuses the update on a locked package', function (): void {
    $this->package->update([
        'status' => PackageStatus::APPROVED,
        'locked_at' => now(),
        'locked_by' => $this->manager->id,
    ]);

    $this->actingAs($this->technician)
        ->put(route('packages.site-info.update', $this->package), [
            'scope_of_work' => 'Should never be stored',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($this->package->siteInfo()->first())->toBeNull();
});

it('refuses a manager with no relationship to the package', function (): void {
    $outsider = User::factory()->create()->assignRole(Role::MANAGER->value);
    $outsider->markets()->attach($this->market->id);

    $this->actingAs($outsider)
        ->put(route('packages.site-info.update', $this->package), [
            'scope_of_work' => 'Should never be stored',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($this->package->siteInfo()->first())->toBeNull();
});

it('exposes scope and site access on the package payload for the pre-construction step', function (): void {
    $this->withoutVite();

    $this->package->siteInfo()->create([
        'address' => '742 Evergreen Terrace',
        'scope_of_work' => 'Swap the sector 1 antenna.',
        'site_access_notes' => 'Access via North Gate. Gate code #4921.',
    ]);

    $this->actingAs($this->manager)
        ->get(route('packages.show', $this->package))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Packages/Show')
                ->where('package.site_info.address', '742 Evergreen Terrace')
                ->where('package.site_info.scope_of_work', 'Swap the sector 1 antenna.')
                ->where('package.site_info.site_access_notes', 'Access via North Gate. Gate code #4921.')
        );
});

it('measures the scope of work limit in the characters the user typed', function (): void {
    // Same browser behaviour as the issue description: textarea newlines reach
    // the server as CRLF, so a 2,500 character scope with line breaks must not
    // be rejected. Pinned here so the fix is a class fix, not a one-field one.
    $scope = str_repeat('a', 2496)."\r\n\r\n".str_repeat('b', 2);

    $this->actingAs($this->manager)
        ->put(route('packages.site-info.update', $this->package), [
            'scope_of_work' => $scope,
        ])
        ->assertSessionHasNoErrors();

    $stored = $this->package->siteInfo()->firstOrFail()->scope_of_work;

    expect(mb_strlen($stored))->toBe(2500)
        ->and($stored)->not->toContain("\r");
});

it('still rejects a scope of work that is genuinely over the limit', function (): void {
    $this->actingAs($this->manager)
        ->put(route('packages.site-info.update', $this->package), [
            'scope_of_work' => str_repeat('a', 2501),
        ])
        ->assertSessionHasErrors('scope_of_work');
});
