<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Dashboard\Services\DashboardQueryService;
use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Actions\AssignManagerAction;
use App\Domains\Projects\Events\ProjectManagerAssigned;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_assigned_manager_is_no_longer_counted_as_needing_manager(): void
    {
        $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
        $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
        $market = Market::factory()->create();
        $manager->markets()->attach($market->id);

        $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => null]);

        expect(app(DashboardQueryService::class)->statsFor($admin)->projectsNeedingManager)
            ->toBe(1);

        app(AssignManagerAction::class)->execute($project, $manager->id, $admin);

        expect(app(DashboardQueryService::class)->statsFor($admin)->projectsNeedingManager)
            ->toBe(0);
    }

    public function test_dashboard_cache_is_flushed_when_a_manager_is_assigned(): void
    {
        $tagged = \Mockery::mock();
        $tagged->shouldReceive('flush')->once();

        Cache::shouldReceive('supportsTags')->andReturn(true);
        Cache::shouldReceive('tags')->with(['dashboard'])->andReturn($tagged);

        $project = Project::factory()->create();
        $manager = User::factory()->create();

        // Dispatch through the real dispatcher so the auto-discovered
        // listener binding is exercised, not just the handler in isolation.
        ProjectManagerAssigned::dispatch($project, $manager);
    }
}
