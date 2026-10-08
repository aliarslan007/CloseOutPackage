<?php

declare(strict_types=1);

use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\CustomerPortal\Notifications\CustomerPortalAccessNotification;
use App\Domains\CustomerPortal\Notifications\CustomerPortalAccessRevokedNotification;
use App\Domains\CustomerPortal\Notifications\ManagerCustomerAccessNotification;
use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->market = Market::factory()->create();
    $this->admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $this->manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $this->otherManager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $this->customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);

    $this->assignedProject = Project::factory()->create([
        'market_id' => $this->market->id,
        'manager_id' => $this->manager->id,
        'name' => 'Assigned Project Alpha',
    ]);

    $this->unassignedProject = Project::factory()->create([
        'market_id' => $this->market->id,
        'manager_id' => $this->otherManager->id,
        'name' => 'Unassigned Project Beta',
    ]);
});

it('scopes the customer access index to assigned projects for a manager while admin sees all', function (): void {
    $this->withoutVite();

    CustomerProjectAccess::factory()->create([
        'user_id' => $this->customer->id,
        'project_id' => $this->assignedProject->id,
        'granted_by' => $this->manager->id,
    ]);

    CustomerProjectAccess::factory()->create([
        'user_id' => $this->customer->id,
        'project_id' => $this->unassignedProject->id,
        'granted_by' => $this->otherManager->id,
    ]);

    // Manager views the page
    $this->actingAs($this->manager)
        ->get(route('admin.customers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Customers/Index')
            // Manager only sees their assigned project in available options
            ->has('projects', 1)
            ->where('projects.0.id', $this->assignedProject->id)
            // Manager only sees access items for their assigned project
            ->has('customers.0.customer_accesses', 1)
            ->where('customers.0.customer_accesses.0.project_id', $this->assignedProject->id)
        );

    // Admin views the page
    $this->actingAs($this->admin)
        ->get(route('admin.customers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Customers/Index')
            // Admin sees all projects
            ->has('projects', 2)
            // Admin sees all customer accesses
            ->has('customers.0.customer_accesses', 2)
        );
});

it('allows a manager to grant access to an assigned project and notifies both customer and admins', function (): void {
    Notification::fake();

    $this->actingAs($this->manager)
        ->post(route('admin.customers.access.store', $this->customer), [
            'user_id' => $this->customer->id,
            'project_id' => $this->assignedProject->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('customer_project_access', [
        'user_id' => $this->customer->id,
        'project_id' => $this->assignedProject->id,
        'granted_by' => $this->manager->id,
    ]);

    Notification::assertSentTo($this->customer, CustomerPortalAccessNotification::class);
    Notification::assertSentTo($this->admin, ManagerCustomerAccessNotification::class, function (ManagerCustomerAccessNotification $notification): bool {
        return $notification->action === 'granted'
            && $notification->manager->id === $this->manager->id
            && $notification->customer->id === $this->customer->id
            && $notification->project->id === $this->assignedProject->id;
    });
});

it('rejects a manager attempting to grant access to an unassigned project', function (): void {
    Notification::fake();

    $this->actingAs($this->manager)
        ->post(route('admin.customers.access.store', $this->customer), [
            'user_id' => $this->customer->id,
            'project_id' => $this->unassignedProject->id,
        ])
        ->assertSessionHasErrors('project_id');

    $this->assertDatabaseMissing('customer_project_access', [
        'user_id' => $this->customer->id,
        'project_id' => $this->unassignedProject->id,
    ]);

    Notification::assertNothingSent();
});

it('allows a manager to revoke customer access for an assigned project and notifies customer and admins', function (): void {
    Notification::fake();

    $access = CustomerProjectAccess::factory()->create([
        'user_id' => $this->customer->id,
        'project_id' => $this->assignedProject->id,
        'granted_by' => $this->manager->id,
    ]);

    $this->actingAs($this->manager)
        ->delete(route('admin.customers.access.destroy', [$this->customer, $this->assignedProject]), [
            'user_id' => $this->customer->id,
            'project_id' => $this->assignedProject->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(CustomerProjectAccess::find($access->id))->toBeNull();

    Notification::assertSentTo($this->customer, CustomerPortalAccessRevokedNotification::class);
    Notification::assertSentTo($this->admin, ManagerCustomerAccessNotification::class, function (ManagerCustomerAccessNotification $notification): bool {
        return $notification->action === 'revoked'
            && $notification->manager->id === $this->manager->id
            && $notification->customer->id === $this->customer->id
            && $notification->project->id === $this->assignedProject->id;
    });
});

it('forbids a manager from revoking customer access for an unassigned project', function (): void {
    Notification::fake();

    $access = CustomerProjectAccess::factory()->create([
        'user_id' => $this->customer->id,
        'project_id' => $this->unassignedProject->id,
        'granted_by' => $this->otherManager->id,
    ]);

    $this->actingAs($this->manager)
        ->delete(route('admin.customers.access.destroy', [$this->customer, $this->unassignedProject]), [
            'user_id' => $this->customer->id,
            'project_id' => $this->unassignedProject->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');

    expect(CustomerProjectAccess::find($access->id))->not->toBeNull();
    Notification::assertNothingSent();
});

it('does not send manager-action notifications to admins when an admin grants or revokes customer access', function (): void {
    Notification::fake();

    // Admin grants access
    $this->actingAs($this->admin)
        ->post(route('admin.customers.access.store', $this->customer), [
            'user_id' => $this->customer->id,
            'project_id' => $this->assignedProject->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($this->customer, CustomerPortalAccessNotification::class);
    Notification::assertNotSentTo($this->admin, ManagerCustomerAccessNotification::class);

    // Admin revokes access
    $this->actingAs($this->admin)
        ->delete(route('admin.customers.access.destroy', [$this->customer, $this->assignedProject]), [
            'user_id' => $this->customer->id,
            'project_id' => $this->assignedProject->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($this->customer, CustomerPortalAccessRevokedNotification::class);
    Notification::assertNotSentTo($this->admin, ManagerCustomerAccessNotification::class);
});

it('allows a manager assigned via fieldSupervisors to grant and revoke customer access', function (): void {
    Notification::fake();

    $supervisedProject = Project::factory()->create([
        'market_id' => $this->market->id,
        'manager_id' => $this->otherManager->id,
        'name' => 'Field Supervised Project',
    ]);
    $supervisedProject->fieldSupervisors()->attach($this->manager->id);

    // Manager grants access
    $this->actingAs($this->manager)
        ->post(route('admin.customers.access.store', $this->customer), [
            'user_id' => $this->customer->id,
            'project_id' => $supervisedProject->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('customer_project_access', [
        'user_id' => $this->customer->id,
        'project_id' => $supervisedProject->id,
        'granted_by' => $this->manager->id,
    ]);

    // Manager revokes access
    $this->actingAs($this->manager)
        ->delete(route('admin.customers.access.destroy', [$this->customer, $supervisedProject]), [
            'user_id' => $this->customer->id,
            'project_id' => $supervisedProject->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('customer_project_access', [
        'user_id' => $this->customer->id,
        'project_id' => $supervisedProject->id,
    ]);
});
