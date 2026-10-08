<?php

use App\Domains\CloseoutPackages\Actions\CreatePackageAction;
use App\Domains\CloseoutPackages\Actions\DeletePackageAction;
use App\Domains\CloseoutPackages\Actions\IncrementRevisionAction;
use App\Domains\CloseoutPackages\Actions\RestorePackageAction;
use App\Domains\CloseoutPackages\DTOs\CreatePackageDTO;
use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\CloseoutPackages\Services\StageAdvancementService;
use App\Domains\Markets\Models\Market;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Actions\UpdateProjectProgressAction;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Services\ProjectProgressService;
use App\Domains\Templates\Enums\StageType;
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

it('advances through the stage sequence and stops after the last stage', function (): void {
    $service = app(StageAdvancementService::class);

    expect($service->next(StageType::PRE_CONSTRUCTION))->toBe(StageType::CONSTRUCTION)
        ->and($service->next(StageType::CONSTRUCTION))->toBe(StageType::POST_COMPLETION)
        ->and($service->next(StageType::POST_COMPLETION))->toBeNull()
        ->and($service->isLast(StageType::POST_COMPLETION))->toBeTrue()
        ->and($service->isLast(StageType::PRE_CONSTRUCTION))->toBeFalse();
});

it('seeds the exact number of selections and responses from the template', function (): void {
    $market = Market::factory()->create();
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create(['market_id' => $market->id]);
    $template = Template::factory()->create(['market_id' => $market->id]);

    $pre = $template->sections()->where('stage', StageType::PRE_CONSTRUCTION)->firstOrFail();
    TemplatePictureCategory::factory()->count(3)->create(['template_section_id' => $pre->id]);
    $con = $template->sections()->where('stage', StageType::CONSTRUCTION)->firstOrFail();
    TemplatePictureCategory::factory()->count(2)->create(['template_section_id' => $con->id]);
    ChecklistItem::factory()->count(4)->create(['template_id' => $template->id]);

    $package = app(CreatePackageAction::class)->execute(
        new CreatePackageDTO(projectId: $project->id, templateId: $template->id),
        $admin,
    );

    expect($package->stages()->count())->toBe(3);

    $preStage = $package->stages()->where('stage', StageType::PRE_CONSTRUCTION)->firstOrFail();
    $conStage = $package->stages()->where('stage', StageType::CONSTRUCTION)->firstOrFail();
    $postStage = $package->stages()->where('stage', StageType::POST_COMPLETION)->firstOrFail();

    expect($preStage->categorySelections()->count())->toBe(3)
        ->and($conStage->categorySelections()->count())->toBe(2)
        ->and($postStage->categorySelections()->count())->toBe(0)
        ->and($package->checklistResponses()->count())->toBe(4);
});

it('initializes first package as R1.0 and subsequent package on the same project as R1.1', function (): void {
    $market = Market::factory()->create();
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create(['market_id' => $market->id]);
    $template = Template::factory()->create(['market_id' => $market->id]);

    $pkg1 = app(CreatePackageAction::class)->execute(
        new CreatePackageDTO(projectId: $project->id, templateId: $template->id),
        $admin,
    );

    expect($pkg1->revision_major)->toBe(1)
        ->and($pkg1->revision_minor)->toBe(0);

    $pkg2 = app(CreatePackageAction::class)->execute(
        new CreatePackageDTO(projectId: $project->id, templateId: $template->id),
        $admin,
    );

    expect($pkg2->revision_major)->toBe(1)
        ->and($pkg2->revision_minor)->toBe(1);
});

it('increments the revision minor and wraps to the next major at the threshold', function (): void {
    $package = CloseoutPackage::factory()->create(['revision_major' => 1, 'revision_minor' => 3]);

    $updated = app(IncrementRevisionAction::class)->execute($package);

    expect($updated->revision_major)->toBe(1)
        ->and($updated->revision_minor)->toBe(4)
        ->and($updated->revision_date)->not->toBeNull();

    $package->update(['revision_major' => 2, 'revision_minor' => 9]);

    $wrapped = app(IncrementRevisionAction::class)->execute($package->fresh());

    expect($wrapped->revision_major)->toBe(3)
        ->and($wrapped->revision_minor)->toBe(0);
});

it('computes project progress from uploaded photos vs required', function (): void {
    $market = Market::factory()->create();
    $project = Project::factory()->create(['market_id' => $market->id]);
    $template = Template::factory()->create(['market_id' => $market->id]);

    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
    ]);

    $stage = PackageStage::factory()->create([
        'closeout_package_id' => $package->id,
        'stage' => StageType::PRE_CONSTRUCTION,
    ]);
    $requiredA = TemplatePictureCategory::factory()->create(['is_required' => true]);
    $requiredB = TemplatePictureCategory::factory()->create(['is_required' => true]);
    PackageCategorySelection::factory()->create([
        'package_stage_id' => $stage->id,
        'template_picture_category_id' => $requiredA->id,
        'is_selected' => true,
        'picture_count' => 1,
    ]);
    PackageCategorySelection::factory()->create([
        'package_stage_id' => $stage->id,
        'template_picture_category_id' => $requiredB->id,
        'is_selected' => true,
        'picture_count' => 2,
    ]);

    expect(app(ProjectProgressService::class)->calculate($project))->toBe(0);

    Photo::factory()->create([
        'closeout_package_id' => $package->id,
        'package_stage_id' => $stage->id,
        'package_category_selection_id' => 1,
        'is_approved' => false,
    ]);

    expect(app(ProjectProgressService::class)->calculate($project))->toBe(33);

    Photo::factory()->count(2)->create([
        'closeout_package_id' => $package->id,
        'package_stage_id' => $stage->id,
        'package_category_selection_id' => 1,
    ]);

    expect(app(ProjectProgressService::class)->calculate($project))->toBe(100);
});

it('does not double count replaced photos in project progress', function (): void {
    $market = Market::factory()->create();
    $project = Project::factory()->create(['market_id' => $market->id]);
    $template = Template::factory()->create(['market_id' => $market->id]);

    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
    ]);

    $stage = PackageStage::factory()->create([
        'closeout_package_id' => $package->id,
    ]);
    $requiredCategory = TemplatePictureCategory::factory()->create(['is_required' => true]);
    PackageCategorySelection::factory()->create([
        'package_stage_id' => $stage->id,
        'template_picture_category_id' => $requiredCategory->id,
        'is_selected' => true,
        'picture_count' => 1,
    ]);

    $old = Photo::factory()->create([
        'closeout_package_id' => $package->id,
        'package_stage_id' => $stage->id,
    ]);
    $old->update(['replaced_at' => now()]);
    Photo::factory()->create([
        'closeout_package_id' => $package->id,
        'package_stage_id' => $stage->id,
    ]);

    expect(app(ProjectProgressService::class)->calculate($project))->toBe(100);
});

it('reports zero progress for a project without packages', function (): void {
    $project = Project::factory()->create();

    expect(app(ProjectProgressService::class)->calculate($project))->toBe(0);
});

it('resets project progress to 0 when all packages are deleted and restores it on package restore', function (): void {
    $market = Market::factory()->create();
    $project = Project::factory()->create(['market_id' => $market->id]);
    $template = Template::factory()->create(['market_id' => $market->id]);
    $user = User::factory()->create()->assignRole(Role::ADMIN->value);
    $this->actingAs($user);

    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
        'status' => PackageStatus::APPROVED,
    ]);

    app(UpdateProjectProgressAction::class)->execute($project);

    expect($project->fresh()->progress_percentage)->toBe(100)
        ->and($project->fresh()->status->value)->toBe('completed');

    // Delete the package
    app(DeletePackageAction::class)->execute($package);

    expect($project->fresh()->progress_percentage)->toBe(0)
        ->and($project->fresh()->status->value)->toBe('pending');

    // Restore the package
    app(RestorePackageAction::class)->execute($package);

    expect($project->fresh()->progress_percentage)->toBe(100)
        ->and($project->fresh()->status->value)->toBe('completed');
});
