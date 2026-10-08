<?php

use App\Domains\Email\Models\EmailLog;
use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('lets an admin bulk delete selected email logs', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    $keep = EmailLog::factory()->create();
    $targets = EmailLog::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get(route('admin.email-logs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can_delete', true));

    $response = $this->actingAs($admin)
        ->delete(route('admin.email-logs.destroy'), ['ids' => $targets->pluck('id')->all()]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(EmailLog::count())->toBe(1)
        ->and(EmailLog::first()->id)->toBe($keep->id);
});

it('keeps managers read-only on the email audit log', function (): void {
    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    $ownProject = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
    $own = EmailLog::factory()->create(['project_id' => $ownProject->id]);

    // Managers read the log, so the page must not offer deletion.
    $this->actingAs($manager)
        ->get(route('manager.email-logs.manager'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can_delete', false));

    // Deletion is admin-only: no manager route exists, and posting to the
    // admin endpoint is refused by the role middleware.
    expect(Route::has('manager.email-logs.destroy'))->toBeFalse();

    $this->actingAs($manager)
        ->delete(route('admin.email-logs.destroy'), ['ids' => [$own->id]])
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');

    expect(EmailLog::whereKey($own->id)->exists())->toBeTrue();
});

it('hides the delete control from roles outside the email log pages', function (string $role): void {
    $user = User::factory()->create()->assignRole($role);

    $this->actingAs($user)
        ->delete(route('admin.email-logs.destroy'), ['ids' => [EmailLog::factory()->create()->id]])
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');

    expect(EmailLog::count())->toBe(1);
})->with([Role::FIELD_SUPERVISOR->value, Role::CUSTOMER->value]);

it('validates that at least one id is provided', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    $this->actingAs($admin)
        ->delete(route('admin.email-logs.destroy'), ['ids' => []])
        ->assertRedirect()
        ->assertSessionHasErrors('ids');
});
