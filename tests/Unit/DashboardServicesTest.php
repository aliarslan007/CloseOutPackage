<?php

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use App\Domains\CloseoutPackages\Models\PackageChecklistResponse;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\Dashboard\Services\DashboardQueryService;
use App\Domains\Dashboard\Services\LiveProjectVisibilityService;
use App\Domains\Email\Enums\EmailStatus;
use App\Domains\Email\Models\EmailLog;
use App\Domains\Markets\Models\Market;
use App\Domains\Photos\Enums\PhotoStatus;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Models\IssueHistory;
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

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('computes dashboard aggregates for admins', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create(['name' => 'Ohio']);
    $active = Project::factory()->create(['market_id' => $market->id]);
    $completed = Project::factory()->create(['market_id' => $market->id, 'status' => 'completed']);
    $template = Template::factory()->create(['market_id' => $market->id]);

    $submitted = CloseoutPackage::factory()->create([
        'project_id' => $active->id,
        'template_id' => $template->id,
        'status' => PackageStatus::SUBMITTED->value,
        'submitted_at' => now(),
    ]);
    $approved = CloseoutPackage::factory()->create([
        'project_id' => $completed->id,
        'template_id' => $template->id,
        'status' => PackageStatus::APPROVED->value,
        'approved_at' => now(),
    ]);

    $stats = app(DashboardQueryService::class)->statsFor($admin);

    expect($stats->totalProjects)->toBe(2)
        ->and($stats->projectsPendingSubmission)->toBe(1)
        ->and($stats->completedProjects)->toBe(1)
        ->and($stats->pendingApprovals)->toBe(1)
        ->and($stats->recentSubmissions)->toBe(1)
        ->and($stats->marketHealth)->toHaveCount(1)
        ->and($stats->marketHealth[0]['market'])->toBe('Ohio')
        ->and($stats->marketHealth[0]['count'])->toBe(2)
        ->and($stats->marketHealth[0]['completed'])->toBe(1)
        ->and($stats->marketHealth[0]['attention'])->toBe(1);
});

it('counts flagged photos and averages project progress for the scope', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();
    $projectA = Project::factory()->create(['market_id' => $market->id, 'progress_percentage' => 50]);
    $projectB = Project::factory()->create(['market_id' => $market->id, 'progress_percentage' => 100]);
    $template = Template::factory()->create(['market_id' => $market->id]);
    $package = CloseoutPackage::factory()->create([
        'project_id' => $projectA->id,
        'template_id' => $template->id,
    ]);
    $stage = PackageStage::factory()->create(['closeout_package_id' => $package->id]);
    $selection = PackageCategorySelection::factory()->create(['package_stage_id' => $stage->id]);

    Photo::factory()->create([
        'closeout_package_id' => $package->id,
        'package_stage_id' => $stage->id,
        'package_category_selection_id' => $selection->id,
        'status' => PhotoStatus::FLAGGED->value,
    ]);
    Photo::factory()->create([
        'closeout_package_id' => $package->id,
        'package_stage_id' => $stage->id,
        'package_category_selection_id' => $selection->id,
        'status' => PhotoStatus::REPLACEMENT_REQUESTED->value,
        'replaced_at' => now(),
    ]);
    Photo::factory()->create([
        'closeout_package_id' => $package->id,
        'package_stage_id' => $stage->id,
        'package_category_selection_id' => $selection->id,
        'status' => PhotoStatus::PENDING->value,
    ]);

    IssueHistory::factory()->create(['project_id' => $projectA->id]);
    IssueHistory::factory()->create(['project_id' => $projectB->id, 'resolved_at' => now()]);

    $stats = app(DashboardQueryService::class)->statsFor($admin);

    expect($stats->flaggedPhotos)->toBe(1)
        ->and($stats->photosAwaitingReview)->toBe(1)
        ->and($stats->openIssues)->toBe(1)
        ->and($stats->avgProgress)->toBe(75);
});

it('computes lifecycle, pipeline, and communication metrics', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $market = Market::factory()->create();

    $unassigned = Project::factory()->create(['market_id' => $market->id, 'manager_id' => null]);
    $needsSupervisor = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
    $notStarted = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
    $inProgress = Project::factory()->create([
        'market_id' => $market->id,
        'manager_id' => $manager->id,
        'progress_percentage' => 40,
    ]);
    $completed = Project::factory()->create(['market_id' => $market->id, 'status' => 'completed']);

    $notStarted->fieldSupervisors()->attach($supervisor->id);
    $inProgress->fieldSupervisors()->attach($supervisor->id);

    $template = Template::factory()->create(['market_id' => $market->id]);
    $approved = CloseoutPackage::factory()->create([
        'project_id' => $inProgress->id,
        'template_id' => $template->id,
        'status' => PackageStatus::APPROVED->value,
    ]);
    $rejected = CloseoutPackage::factory()->create([
        'project_id' => $inProgress->id,
        'template_id' => $template->id,
        'status' => PackageStatus::REJECTED->value,
    ]);
    $awaiting = CloseoutPackage::factory()->create([
        'project_id' => $inProgress->id,
        'template_id' => $template->id,
        'status' => PackageStatus::SUBMITTED->value,
        'submitted_at' => now()->startOfDay(),
    ]);
    $draft = CloseoutPackage::factory()->create([
        'project_id' => $inProgress->id,
        'template_id' => $template->id,
    ]);

    // A draft with an active flagged photo is also "needs rework".
    $stage = PackageStage::factory()->create(['closeout_package_id' => $draft->id]);
    $selection = PackageCategorySelection::factory()->create(['package_stage_id' => $stage->id]);
    Photo::factory()->create([
        'closeout_package_id' => $draft->id,
        'package_stage_id' => $stage->id,
        'package_category_selection_id' => $selection->id,
        'status' => PhotoStatus::FLAGGED->value,
    ]);

    EmailLog::factory()->create([
        'project_id' => $inProgress->id,
        'status' => EmailStatus::SENT->value,
        'sent_at' => now(),
    ]);
    EmailLog::factory()->create([
        'project_id' => $inProgress->id,
        'status' => EmailStatus::FAILED->value,
        'sent_at' => null,
    ]);

    $stats = app(DashboardQueryService::class)->statsFor($admin);

    expect($stats->totalProjects)->toBe(5)
        ->and($stats->projectsNeedingManager)->toBe(1)
        ->and($stats->projectsNeedingSupervisor)->toBe(1)
        ->and($stats->projectsNotStarted)->toBe(1)
        ->and($stats->projectsInProgress)->toBe(1)
        ->and($stats->completedProjects)->toBe(1)
        ->and($stats->totalPackages)->toBe(4)
        ->and($stats->packagesApproved)->toBe(1)
        ->and($stats->packagesRejected)->toBe(1)
        ->and($stats->packagesDraft)->toBe(1)
        ->and($stats->pendingApprovals)->toBe(1)
        ->and($stats->packagesWithFlaggedPhotos)->toBe(1)
        ->and($stats->packagesNeedingRework)->toBe(2)
        ->and($stats->photosCaptured)->toBe(1)
        ->and($stats->emailsSentToday)->toBe(1)
        ->and($stats->emailsFailedToday)->toBe(1)
        // No project here has both history and quiet activity.
        ->and($stats->projectsStalled)->toBe(0)
        // Momentum covers 30 days; today shows the single submission.
        ->and($stats->momentum)->toHaveCount(30)
        ->and($stats->momentum[29]['submissions'])->toBe(1)
        ->and($stats->momentum[29]['approvals'])->toBe(0);
});

it('flags only staffed projects with stale activity as stalled', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $market = Market::factory()->create();
    $template = Template::factory()->create(['market_id' => $market->id]);

    // Staffed project with an old package and no recent activity — stalled.
    $quiet = Project::factory()->create([
        'market_id' => $market->id,
        'manager_id' => $manager->id,
        'progress_percentage' => 20,
    ]);
    $quiet->fieldSupervisors()->attach($supervisor->id);
    $oldPackage = CloseoutPackage::factory()->create([
        'project_id' => $quiet->id,
        'template_id' => $template->id,
    ]);
    $oldPackage->forceFill([
        'created_at' => now()->subDays(40),
        'updated_at' => now()->subDays(40),
    ])->save();

    // Brand-new staffed project with no packages yet — NOT stalled.
    $fresh = Project::factory()->create([
        'market_id' => $market->id,
        'manager_id' => $manager->id,
    ]);
    $fresh->fieldSupervisors()->attach($supervisor->id);

    // Staffed project with recent package activity — NOT stalled.
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

    $stats = app(DashboardQueryService::class)->statsFor($admin);

    expect($stats->projectsStalled)->toBe(1);
});

it('counts draft packages whose supervisor work is complete as ready to submit', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $market = Market::factory()->create();
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
    $template = Template::factory()->create(['market_id' => $market->id]);
    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
    ]);

    // Supervisor-controlled requirements: checklist, site info, required photo.
    $checklist = ChecklistItem::factory()->create(['template_id' => $template->id]);
    PackageChecklistResponse::factory()->create([
        'closeout_package_id' => $package->id,
        'checklist_item_id' => $checklist->id,
        'answer' => true,
    ]);
    $package->siteInfo()->create([
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
    $stage = PackageStage::factory()->create(['closeout_package_id' => $package->id]);
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
        'closeout_package_id' => $package->id,
        'package_stage_id' => $stage->id,
        'package_category_selection_id' => $selection->id,
        'status' => PhotoStatus::PENDING->value,
    ]);

    $stats = app(DashboardQueryService::class)->statsFor($admin);

    expect($stats->packagesReadyToSubmit)->toBe(1)
        ->and($stats->packagesDraft)->toBe(1)
        ->and($stats->totalPackages)->toBe(1);
});

it('scopes attention items and email metrics by role', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $market = Market::factory()->create();
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
    $template = Template::factory()->create(['market_id' => $market->id]);
    CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
        'status' => PackageStatus::SUBMITTED->value,
        'submitted_at' => now(),
    ]);
    EmailLog::factory()->create(['project_id' => $project->id, 'status' => EmailStatus::SENT->value]);

    $managerStats = app(DashboardQueryService::class)->statsFor($manager);
    $supervisorStats = app(DashboardQueryService::class)->statsFor($supervisor);

    // The manager's attention panel leads with "awaiting your review" and sees email volume.
    expect($managerStats->attentionItems[0]['label'])->toBe('Packages await your review')
        ->and($managerStats->emailsSentToday)->toBe(1)
        // The supervisor only sees their own projects (none assigned here) and no email stats.
        ->and($supervisorStats->totalProjects)->toBe(0)
        ->and($supervisorStats->emailsSentToday)->toBe(0)
        ->and($supervisorStats->attentionItems)->toBe([]);
});

it('builds the live visibility snapshot for the assigned manager', function (): void {
    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);

    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
    $template = Template::factory()->create(['market_id' => $market->id]);
    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
    ]);
    Photo::factory()->create(['closeout_package_id' => $package->id]);

    $snapshot = app(LiveProjectVisibilityService::class)->snapshot($manager);

    expect($snapshot['projects'])->toHaveCount(1)
        ->and($snapshot['templateCount'])->toBe(1)
        ->and($snapshot['photoCount'])->toBe(1)
        ->and($snapshot['generatedAt'])->not->toBeNull();
});
