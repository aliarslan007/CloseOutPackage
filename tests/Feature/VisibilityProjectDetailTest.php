<?php

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Enums\StageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageChecklistResponse;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\Photos\Enums\PhotoStatus;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Models\IssueHistory;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Enums\StageType;
use App\Domains\Templates\Models\ChecklistItem;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Storage::fake('private');
});

it('exposes the full project detail payload to admins', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $project->fieldSupervisors()->attach($supervisor->id);

    IssueHistory::factory()->create(['project_id' => $project->id, 'reported_by' => $supervisor->id]);
    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'status' => PackageStatus::SUBMITTED,
    ]);

    $this->actingAs($admin)
        ->get(route('manager.visibility.project', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Manager/Visibility/Project')
            ->where('project.id', $project->id)
            ->where('project.name', $project->name)
            ->where('project.status', $project->status->value)
            ->where('project.manager.id', $manager->id)
            ->where('project.market.id', $project->market_id)
            ->has('project.field_supervisors', 1)
            ->has('project.issues.data', 1)
            ->has('project.closeout_packages', 1)
            ->where('project.closeout_packages.0.id', $package->id)
            ->where('project.closeout_packages.0.status', $package->status->value)
            ->where('project.closeout_packages.0.template.id', $package->template_id));
});

it('exposes package stages, photos, and checklist payload for the live view', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create();
    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'status' => PackageStatus::APPROVED,
    ]);

    $preConstruction = PackageStage::factory()->create([
        'closeout_package_id' => $package->id,
        'stage' => StageType::PRE_CONSTRUCTION,
        'status' => StageStatus::SUBMITTED,
    ]);
    $construction = PackageStage::factory()->create([
        'closeout_package_id' => $package->id,
        'stage' => StageType::CONSTRUCTION,
        'status' => StageStatus::SUBMITTED,
    ]);
    $postCompletion = PackageStage::factory()->create([
        'closeout_package_id' => $package->id,
        'stage' => StageType::POST_COMPLETION,
        'status' => StageStatus::NOT_STARTED,
    ]);

    $approvedPhoto = Photo::factory()->create([
        'closeout_package_id' => $package->id,
        'package_stage_id' => $preConstruction->id,
        'position' => 1,
        'status' => PhotoStatus::APPROVED,
        'gps_latitude' => 40.1234567,
        'gps_longitude' => -82.1234567,
    ]);
    Photo::factory()->create([
        'closeout_package_id' => $package->id,
        'package_stage_id' => $construction->id,
        'position' => 1,
        'status' => PhotoStatus::PENDING,
    ]);
    Photo::factory()->create([
        'closeout_package_id' => $package->id,
        'package_stage_id' => $postCompletion->id,
        'position' => 1,
        'status' => PhotoStatus::FLAGGED,
        'flag_reason' => 'Blurry image',
    ]);

    $yesItem = ChecklistItem::factory()->create();
    $noItem = ChecklistItem::factory()->create();
    $openItem = ChecklistItem::factory()->create();

    PackageChecklistResponse::factory()->create([
        'closeout_package_id' => $package->id,
        'checklist_item_id' => $yesItem->id,
        'answer' => true,
    ]);
    PackageChecklistResponse::factory()->create([
        'closeout_package_id' => $package->id,
        'checklist_item_id' => $noItem->id,
        'answer' => false,
        'explain' => 'Cable run not finalised.',
    ]);
    PackageChecklistResponse::factory()->create([
        'closeout_package_id' => $package->id,
        'checklist_item_id' => $openItem->id,
        'answer' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('manager.visibility.project', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('project.closeout_packages.0.stages', 3)
            ->where('project.closeout_packages.0.stages.0.stage', StageType::PRE_CONSTRUCTION->value)
            ->where('project.closeout_packages.0.stages.0.status', StageStatus::SUBMITTED->value)
            ->where('project.closeout_packages.0.stages.0.photos.0.id', $approvedPhoto->id)
            ->where('project.closeout_packages.0.stages.0.photos.0.status', PhotoStatus::APPROVED->value)
            ->where('project.closeout_packages.0.stages.0.photos.0.gps_latitude', 40.1234567)
            ->where('project.closeout_packages.0.stages.0.photos.0.gps_longitude', -82.1234567)
            ->where('project.closeout_packages.0.stages.0.photos.0.thumb_url', route('photos.signed-url', ['package' => $package->id, 'photo' => $approvedPhoto->id]).'?conversion=thumb')
            ->where('project.closeout_packages.0.photos_count', 3)
            ->has('project.closeout_packages.0.checklist_responses', 3)
            ->where('project.closeout_packages.0.checklist_responses.0.answer', true)
            ->where('project.closeout_packages.0.checklist_responses.0.checklist_item.id', $yesItem->id)
            ->where('project.closeout_packages.0.checklist_responses.1.answer', false)
            ->where('project.closeout_packages.0.checklist_responses.1.explain', 'Cable run not finalised.')
            ->where('project.closeout_packages.0.checklist_responses.2.answer', null)
            ->where('project.closeout_packages.0.checklist_responses_count', 3)
            ->where('project.closeout_packages.0.answered_checklist_responses', 2));
});

it('allows a manager to view their own project detail', function (): void {
    $this->withoutVite();

    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);

    $this->actingAs($manager)
        ->get(route('manager.visibility.project', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Manager/Visibility/Project'));
});

it('allows any manager to view a project detail, matching the view policy', function (): void {
    $this->withoutVite();

    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $otherManager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['manager_id' => $otherManager->id]);

    $this->actingAs($manager)
        ->get(route('manager.visibility.project', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Manager/Visibility/Project'));
});

it('denies field supervisors the visibility project detail', function (): void {
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create();

    $this->actingAs($field)
        ->get(route('manager.visibility.project', $project))
        ->assertForbidden();
});

it('includes the site photo url when a site photo exists', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create();

    $media = $project->addMedia(UploadedFile::fake()->image('site.jpg'))
        ->toMediaCollection(Project::SITE_PHOTO_COLLECTION);

    $this->actingAs($admin)
        ->get(route('manager.visibility.project', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sitePhotoUrl', route('projects.site-photo.show', $project).'?v='.$media->id));
});

it('returns a null site photo url when no site photo exists', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create();

    $this->actingAs($admin)
        ->get(route('manager.visibility.project', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('sitePhotoUrl', null));
});

it('denies guests the visibility project detail', function (): void {
    $project = Project::factory()->create();

    $response = $this->get(route('manager.visibility.project', $project));

    expect($response->headers->get('Location'))->toContain('/session-expired');
});

it('orders issues newest first and packages newest first', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create();

    $oldIssue = IssueHistory::factory()->create([
        'project_id' => $project->id,
        'reported_by' => $supervisor->id,
        'created_at' => now()->subDays(2),
    ]);
    $newIssue = IssueHistory::factory()->create([
        'project_id' => $project->id,
        'reported_by' => $supervisor->id,
        'created_at' => now()->subDay(),
    ]);

    $oldPackage = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'created_at' => now()->subDays(2),
    ]);
    $newPackage = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'created_at' => now()->subDay(),
    ]);

    $this->actingAs($admin)
        ->get(route('manager.visibility.project', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('project.issues.data.0.id', $newIssue->id)
            ->where('project.issues.data.1.id', $oldIssue->id)
            ->where('project.closeout_packages.0.id', $newPackage->id)
            ->where('project.closeout_packages.1.id', $oldPackage->id));
});
