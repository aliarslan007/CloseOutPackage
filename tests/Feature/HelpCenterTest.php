<?php

namespace Tests\Feature;

use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_help_center_requires_authentication(): void
    {
        $this->get(route('help.index'))->assertRedirect(route('session.expired'));
    }

    public function test_help_center_renders_for_an_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('help.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Help/Index'));
    }

    public function test_help_center_works_for_customer_role(): void
    {
        $user = User::factory()->create()->assignRole(Role::CUSTOMER->value);

        $this->actingAs($user)
            ->get(route('help.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Help/Index'));
    }
}
