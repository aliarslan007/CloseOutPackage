<?php

namespace Tests\Feature;

use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('session.expired'));
    }

    public function test_dashboard_shows_the_authenticated_user(): void
    {
        $this->withoutVite();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'uuid' => 'uuid-789',
        ])->assignRole(Role::MANAGER->value);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('auth.user.name', 'John Doe')
            ->where('auth.user.email', 'john@example.com')
            ->where('auth.user.uuid', 'uuid-789'));
    }

    public function test_customer_can_view_only_the_dashboard_overview(): void
    {
        $this->withoutVite();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);

        $this->actingAs($customer)
            ->get(route('dashboard').'?tab=activity')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('currentTab', 'overview')
                ->where('activities', null));
    }

    public function test_dashboard_exposes_the_sso_flash_message(): void
    {
        $this->withoutVite();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create()->assignRole(Role::MANAGER->value);

        $response = $this
            ->actingAs($user)
            ->withSession(['success' => 'Login successful!'])
            ->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('flash.success', 'Login successful!'));
    }
}
