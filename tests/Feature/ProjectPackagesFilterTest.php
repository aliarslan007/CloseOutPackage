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

class ProjectPackagesFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_lists_all_packages_for_the_project_without_a_filter(): void
    {
        [$admin, $market, $project, $template] = $this->fixtures();

        $draft = CloseoutPackage::factory()->create([
            'project_id' => $project->id,
            'template_id' => $template->id,
            'status' => PackageStatus::DRAFT->value,
        ]);
        $draft->forceFill(['created_at' => now()->subSecond()])->save();
        $approved = CloseoutPackage::factory()->create([
            'project_id' => $project->id,
            'template_id' => $template->id,
            'status' => PackageStatus::APPROVED->value,
        ]);

        $this->actingAs($admin)
            ->get(route('packages.index', $project))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Packages/Index')
                ->has('packages.data', 2)
                ->where('status', null)
                ->where('packages.data.0.id', $approved->id)
                ->where('packages.data.1.id', $draft->id));
    }

    public function test_awaiting_review_filter_returns_submitted_and_under_review_packages(): void
    {
        [$admin, $market, $project, $template] = $this->fixtures();

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
            ->get(route('packages.index', [$project, 'status' => 'awaiting_review']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Packages/Index')
                ->has('packages.data', 1)
                ->where('status', 'awaiting_review')
                ->where('packages.data.0.id', $submitted->id));
    }

    public function test_needs_rework_filter_includes_rejected_and_flagged_packages(): void
    {
        [$admin, $market, $project, $template] = $this->fixtures();

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
            ->get(route('packages.index', [$project, 'status' => 'needs_rework']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Packages/Index')
                ->has('packages.data', 2)
                ->where('packages.data.0.id', $flagged->id)
                ->where('packages.data.1.id', $rejected->id));
    }

    public function test_ready_to_submit_filter_returns_only_complete_drafts(): void
    {
        [$admin, $market, $project, $template] = $this->fixtures();

        $ready = CloseoutPackage::factory()->create([
            'project_id' => $project->id,
            'template_id' => $template->id,
        ]);
        $incomplete = CloseoutPackage::factory()->create([
            'project_id' => $project->id,
            'template_id' => $template->id,
        ]);

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
            ->get(route('packages.index', [$project, 'status' => 'ready_to_submit']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Packages/Index')
                ->has('packages.data', 1)
                ->where('packages.data.0.id', $ready->id));
    }

    public function test_raw_status_filter_returns_only_matching_packages(): void
    {
        [$admin, $market, $project, $template] = $this->fixtures();

        $approved = CloseoutPackage::factory()->create([
            'project_id' => $project->id,
            'template_id' => $template->id,
            'status' => PackageStatus::APPROVED->value,
        ]);
        CloseoutPackage::factory()->create([
            'project_id' => $project->id,
            'template_id' => $template->id,
            'status' => PackageStatus::DRAFT->value,
        ]);

        $this->actingAs($admin)
            ->get(route('packages.index', [$project, 'status' => 'approved']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Packages/Index')
                ->has('packages.data', 1)
                ->where('packages.data.0.id', $approved->id));
    }

    public function test_filter_is_scoped_to_the_project(): void
    {
        [$admin, $market, $project, $template] = $this->fixtures();
        $other = Project::factory()->create(['market_id' => $market->id]);
        CloseoutPackage::factory()->create([
            'project_id' => $other->id,
            'template_id' => $template->id,
            'status' => PackageStatus::SUBMITTED->value,
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('packages.index', [$project, 'status' => 'awaiting_review']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Packages/Index')
                ->has('packages.data', 0)
                ->where('packages.total', 0));
    }

    /**
     * @return array{0: User, 1: Market, 2: Project, 3: Template}
     */
    private function fixtures(): array
    {
        $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
        $market = Market::factory()->create();
        $project = Project::factory()->create(['market_id' => $market->id]);
        $template = Template::factory()->create(['market_id' => $market->id]);

        return [$admin, $market, $project, $template];
    }
}
