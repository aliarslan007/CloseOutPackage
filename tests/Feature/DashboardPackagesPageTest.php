<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use App\Domains\CloseoutPackages\Models\PackageChecklistResponse;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\Markets\Models\Market;
use App\Domains\Photos\Enums\PhotoStatus;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Models\ChecklistItem;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplatePictureCategory;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardPackagesPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_lists_all_packages_in_scope_for_admins(): void
    {
        $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
        $market = Market::factory()->create();
        $project = Project::factory()->create(['market_id' => $market->id]);
        $template = Template::factory()->create(['market_id' => $market->id]);
        CloseoutPackage::factory()->create([
            'project_id' => $project->id,
            'template_id' => $template->id,
            'status' => PackageStatus::DRAFT->value,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard.packages'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Packages')
                ->has('packages.data', 1)
                ->where('packages.data.0.project.name', $project->name)
                ->where('packages.data.0.template.name', $template->name));
    }

    public function test_awaiting_review_filter_returns_submitted_and_under_review_packages(): void
    {
        $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
        $market = Market::factory()->create();
        $project = Project::factory()->create(['market_id' => $market->id]);
        $template = Template::factory()->create(['market_id' => $market->id]);

        $submitted = CloseoutPackage::factory()->create([
            'project_id' => $project->id,
            'template_id' => $template->id,
            'status' => PackageStatus::SUBMITTED->value,
            'submitted_at' => now(),
        ]);
        CloseoutPackage::factory()->create([
            'project_id' => $project->id,
            'template_id' => $template->id,
            'status' => PackageStatus::DRAFT->value,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard.packages', ['status' => 'awaiting_review']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Packages')
                ->has('packages.data', 1)
                ->where('packages.data.0.id', $submitted->id)
                ->where('packages.data.0.template.name', $template->name));
    }

    public function test_needs_rework_filter_includes_rejected_and_flagged_packages(): void
    {
        $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
        $market = Market::factory()->create();
        $project = Project::factory()->create(['market_id' => $market->id]);
        $template = Template::factory()->create(['market_id' => $market->id]);

        $rejected = CloseoutPackage::factory()->create([
            'project_id' => $project->id,
            'template_id' => $template->id,
            'status' => PackageStatus::REJECTED->value,
        ]);
        $rejected->forceFill(['created_at' => now()->subMinutes(5)])->save();
        $flagged = CloseoutPackage::factory()->create([
            'project_id' => $project->id,
            'template_id' => $template->id,
            'status' => PackageStatus::UNDER_REVIEW->value,
        ]);
        $stage = PackageStage::factory()->create(['closeout_package_id' => $flagged->id]);
        $selection = PackageCategorySelection::factory()->create(['package_stage_id' => $stage->id]);
        Photo::factory()->create([
            'closeout_package_id' => $flagged->id,
            'package_stage_id' => $stage->id,
            'package_category_selection_id' => $selection->id,
            'status' => PhotoStatus::REPLACEMENT_REQUESTED->value,
        ]);
        CloseoutPackage::factory()->create([
            'project_id' => $project->id,
            'template_id' => $template->id,
            'status' => PackageStatus::APPROVED->value,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard.packages', ['status' => 'needs_rework']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Packages')
                ->has('packages.data', 2)
                ->where('packages.data.0.id', $flagged->id)
                ->where('packages.data.1.id', $rejected->id));
    }

    public function test_ready_to_submit_filter_returns_only_complete_drafts(): void
    {
        $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
        $market = Market::factory()->create();
        $project = Project::factory()->create(['market_id' => $market->id]);
        $template = Template::factory()->create(['market_id' => $market->id]);

        $ready = CloseoutPackage::factory()->create([
            'project_id' => $project->id,
            'template_id' => $template->id,
        ]);
        $incomplete = CloseoutPackage::factory()->create([
            'project_id' => $project->id,
            'template_id' => $template->id,
        ]);

        // Complete the supervisor-controlled requirements on the first draft.
        $checklist = ChecklistItem::factory()->create(['template_id' => $template->id]);
        PackageChecklistResponse::factory()->create([
            'closeout_package_id' => $ready->id,
            'checklist_item_id' => $checklist->id,
            'answer' => true,
        ]);
        PackageChecklistResponse::factory()->create([
            'closeout_package_id' => $incomplete->id,
            'checklist_item_id' => $checklist->id,
            'answer' => null,
        ]);
        $ready->siteInfo()->create([
            'site_name' => 'Site',
            'address' => '123 Main St',
            'complete_date' => '2026-08-01',
            'vzw_engineer_name' => 'Engineer',
            'vzw_engineer_email' => 'eng@verizon.example',
            'author_name' => 'Author',
            'author_email' => 'author@example.com',
            'pm_name' => 'Manager',
            'pm_email' => 'manager@example.com',
        ]);
        $stage = PackageStage::factory()->create(['closeout_package_id' => $ready->id]);
        $category = TemplatePictureCategory::factory()->create([
            'template_section_id' => $template->sections()->firstOrFail()->id,
            'is_required' => true,
        ]);
        $selection = PackageCategorySelection::factory()->create([
            'package_stage_id' => $stage->id,
            'template_picture_category_id' => $category->id,
            'picture_count' => 1,
        ]);
        Photo::factory()->create([
            'closeout_package_id' => $ready->id,
            'package_stage_id' => $stage->id,
            'package_category_selection_id' => $selection->id,
            'status' => PhotoStatus::PENDING->value,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard.packages', ['status' => 'ready_to_submit']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Packages')
                ->has('packages.data', 1)
                ->where('packages.data.0.id', $ready->id));
    }

    public function test_package_list_is_scoped_to_the_supervisor(): void
    {
        $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
        $market = Market::factory()->create();
        $assigned = Project::factory()->create(['market_id' => $market->id]);
        $other = Project::factory()->create(['market_id' => $market->id]);
        $assigned->fieldSupervisors()->attach($supervisor->id);

        $template = Template::factory()->create(['market_id' => $market->id]);
        $inScope = CloseoutPackage::factory()->create([
            'project_id' => $assigned->id,
            'template_id' => $template->id,
            'status' => PackageStatus::SUBMITTED->value,
            'submitted_at' => now(),
        ]);
        CloseoutPackage::factory()->create([
            'project_id' => $other->id,
            'template_id' => $template->id,
            'status' => PackageStatus::SUBMITTED->value,
            'submitted_at' => now(),
        ]);

        $this->actingAs($supervisor)
            ->get(route('dashboard.packages', ['status' => 'awaiting_review']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Packages')
                ->has('packages.data', 1)
                ->where('packages.data.0.id', $inScope->id));
    }

    public function test_projects_index_filters_by_assignment_bucket(): void
    {
        $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
        $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
        $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
        $market = Market::factory()->create();

        $unassigned = Project::factory()->create(['market_id' => $market->id, 'manager_id' => null]);
        $needsSupervisor = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
        $inProgress = Project::factory()->create([
            'market_id' => $market->id,
            'manager_id' => $manager->id,
            'progress_percentage' => 40,
        ]);
        $inProgress->fieldSupervisors()->attach($supervisor->id);

        $this->actingAs($admin)
            ->get(route('projects.index', ['assignment' => 'unassigned']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Projects/Index')
                ->where('projects.total', 1)
                ->where('projects.data.0.id', $unassigned->id));

        $this->actingAs($admin)
            ->get(route('projects.index', ['assignment' => 'needs_supervisor']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Projects/Index')
                ->where('projects.total', 1)
                ->where('projects.data.0.id', $needsSupervisor->id));

        $this->actingAs($admin)
            ->get(route('projects.index', ['assignment' => 'in_progress']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Projects/Index')
                ->where('projects.total', 1)
                ->where('projects.data.0.id', $inProgress->id));
    }

    public function test_projects_index_filters_by_stalled_assignment(): void
    {
        $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
        $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
        $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
        $market = Market::factory()->create();

        $stalled = Project::factory()->create([
            'market_id' => $market->id,
            'manager_id' => $manager->id,
        ]);
        $stalled->fieldSupervisors()->attach($supervisor->id);
        $template = Template::factory()->create(['market_id' => $market->id]);
        $oldPackage = CloseoutPackage::factory()->create([
            'project_id' => $stalled->id,
            'template_id' => $template->id,
        ]);
        $oldPackage->forceFill([
            'created_at' => now()->subDays(40),
            'updated_at' => now()->subDays(40),
        ])->save();

        $active = Project::factory()->create([
            'market_id' => $market->id,
            'manager_id' => $manager->id,
            'progress_percentage' => 30,
        ]);
        $active->fieldSupervisors()->attach($supervisor->id);
        CloseoutPackage::factory()->create([
            'project_id' => $active->id,
            'template_id' => $template->id,
        ]);

        $this->actingAs($admin)
            ->get(route('projects.index', ['assignment' => 'stalled']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Projects/Index')
                ->where('projects.total', 1)
                ->where('projects.data.0.id', $stalled->id));
    }
}
