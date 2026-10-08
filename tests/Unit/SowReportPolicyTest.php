<?php

declare(strict_types=1);

use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Models\Project;
use App\Domains\ScopeOfWork\Enums\SowStatus;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\ScopeOfWork\Policies\SowReportPolicy;
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

it('authorizes viewAny for staff and customers but denies unassigned users', function (): void {
    $policy = app(SowReportPolicy::class);

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    $plainUser = User::factory()->create();

    expect($policy->viewAny($admin))->toBeTrue()
        ->and($policy->viewAny($manager))->toBeTrue()
        ->and($policy->viewAny($supervisor))->toBeTrue()
        ->and($policy->viewAny($customer))->toBeTrue()
        ->and($policy->viewAny($plainUser))->toBeFalse();
});

it('authorizes create for admins, managers, and field supervisors only', function (): void {
    $policy = app(SowReportPolicy::class);
    $market = Market::factory()->create();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $managerWithMarket = User::factory()->create()->assignRole(Role::MANAGER->value);
    $managerWithMarket->markets()->attach($market->id);

    $managerWithoutMarket = User::factory()->create()->assignRole(Role::MANAGER->value);

    $supervisorWithMarket = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $supervisorWithMarket->markets()->attach($market->id);

    $supervisorWithoutMarket = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    $plainUser = User::factory()->create();

    expect($policy->create($admin))->toBeTrue()
        ->and($policy->create($managerWithMarket))->toBeTrue()
        ->and($policy->create($managerWithoutMarket))->toBeTrue()
        ->and($policy->create($supervisorWithMarket))->toBeTrue()
        ->and($policy->create($supervisorWithoutMarket))->toBeTrue()
        ->and($policy->create($customer))->toBeFalse()
        ->and($policy->create($plainUser))->toBeFalse();
});

it('restricts customer viewing and downloading to published reports only', function (): void {
    $policy = app(SowReportPolicy::class);
    $market = Market::factory()->create();

    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    $customer->markets()->attach($market->id);

    $creator = User::factory()->create();

    $draftReport = SowReport::query()->create([
        'market_id' => $market->id,
        'created_by' => $creator->id,
        'title' => 'Draft SOW',
        'status' => SowStatus::DRAFT,
    ]);

    $publishedReport = SowReport::query()->create([
        'market_id' => $market->id,
        'created_by' => $creator->id,
        'title' => 'Published SOW',
        'status' => SowStatus::PUBLISHED,
    ]);

    expect($policy->view($customer, $draftReport))->toBeFalse()
        ->and($policy->download($customer, $draftReport))->toBeFalse()
        ->and($policy->view($customer, $publishedReport))->toBeTrue()
        ->and($policy->download($customer, $publishedReport))->toBeTrue();
});

it('authorizes updates on draft and in-review reports for admins, managers, and owning supervisors', function (): void {
    $policy = app(SowReportPolicy::class);
    $market = Market::factory()->create();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);

    $ownerSupervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $ownerSupervisor->markets()->attach($market->id);

    $otherSupervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $otherSupervisor->markets()->attach($market->id);

    $supervisorDraftReport = SowReport::query()->create([
        'market_id' => $market->id,
        'created_by' => $ownerSupervisor->id,
        'title' => 'Supervisor Draft SOW',
        'status' => SowStatus::DRAFT,
    ]);

    $inReviewReport = SowReport::query()->create([
        'market_id' => $market->id,
        'created_by' => $ownerSupervisor->id,
        'title' => 'In Review SOW',
        'status' => SowStatus::IN_REVIEW,
    ]);

    $managerDraftReport = SowReport::query()->create([
        'market_id' => $market->id,
        'created_by' => $manager->id,
        'title' => 'Manager Draft SOW',
        'status' => SowStatus::DRAFT,
    ]);

    $publishedReport = SowReport::query()->create([
        'market_id' => $market->id,
        'created_by' => $ownerSupervisor->id,
        'title' => 'Published SOW',
        'status' => SowStatus::PUBLISHED,
    ]);

    // When draft was created by supervisor: manager/admin view as read-only until submitted
    expect($policy->update($admin, $supervisorDraftReport))->toBeFalse()
        ->and($policy->update($manager, $supervisorDraftReport))->toBeFalse()
        ->and($policy->update($ownerSupervisor, $supervisorDraftReport))->toBeTrue()
        ->and($policy->update($otherSupervisor, $supervisorDraftReport))->toBeFalse();

    // When in review: manager/admin can update and make adjustments; supervisor cannot
    expect($policy->update($admin, $inReviewReport))->toBeTrue()
        ->and($policy->update($manager, $inReviewReport))->toBeTrue()
        ->and($policy->update($ownerSupervisor, $inReviewReport))->toBeFalse()
        ->and($policy->update($otherSupervisor, $inReviewReport))->toBeFalse();

    // When manager authored their own draft: manager and admin can update
    expect($policy->update($manager, $managerDraftReport))->toBeTrue()
        ->and($policy->update($admin, $managerDraftReport))->toBeTrue()
        ->and($policy->update($ownerSupervisor, $managerDraftReport))->toBeFalse();

    // When published: locked for all
    expect($policy->update($admin, $publishedReport))->toBeFalse()
        ->and($policy->update($ownerSupervisor, $publishedReport))->toBeFalse();
});

it('guards submit, publish, reopen, and archive lifecycle state transitions', function (): void {
    $policy = app(SowReportPolicy::class);
    $market = Market::factory()->create();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);

    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $supervisor->markets()->attach($market->id);

    $draftReport = SowReport::query()->create([
        'market_id' => $market->id,
        'created_by' => $supervisor->id,
        'title' => 'Draft SOW',
        'status' => SowStatus::DRAFT,
    ]);

    $inReviewReport = SowReport::query()->create([
        'market_id' => $market->id,
        'created_by' => $supervisor->id,
        'title' => 'In Review SOW',
        'status' => SowStatus::IN_REVIEW,
    ]);

    $managerDraftReport = SowReport::query()->create([
        'market_id' => $market->id,
        'created_by' => $manager->id,
        'title' => 'Manager Draft SOW',
        'status' => SowStatus::DRAFT,
    ]);

    $publishedReport = SowReport::query()->create([
        'market_id' => $market->id,
        'created_by' => $supervisor->id,
        'title' => 'Published SOW',
        'status' => SowStatus::PUBLISHED,
    ]);

    $archivedReport = SowReport::query()->create([
        'market_id' => $market->id,
        'created_by' => $supervisor->id,
        'title' => 'Archived SOW',
        'status' => SowStatus::ARCHIVED,
    ]);

    // Submit: only field supervisor owning the draft can submit; managers/admins cannot submit
    expect($policy->submit($supervisor, $draftReport))->toBeTrue()
        ->and($policy->submit($supervisor, $inReviewReport))->toBeFalse()
        ->and($policy->submit($supervisor, $publishedReport))->toBeFalse()
        ->and($policy->submit($manager, $draftReport))->toBeFalse()
        ->and($policy->submit($manager, $managerDraftReport))->toBeFalse()
        ->and($policy->submit($admin, $draftReport))->toBeFalse();

    // Publish:
    // In-review can be published by admin/manager, supervisor denied
    // Supervisor draft CANNOT be published directly by manager/admin (must wait for submission)
    // Manager self-authored draft CAN be published directly by manager/admin
    expect($policy->publish($manager, $inReviewReport))->toBeTrue()
        ->and($policy->publish($admin, $inReviewReport))->toBeTrue()
        ->and($policy->publish($supervisor, $inReviewReport))->toBeFalse()
        ->and($policy->publish($manager, $draftReport))->toBeFalse()
        ->and($policy->publish($admin, $draftReport))->toBeFalse()
        ->and($policy->publish($manager, $managerDraftReport))->toBeTrue()
        ->and($policy->publish($admin, $managerDraftReport))->toBeTrue()
        ->and($policy->publish($supervisor, $draftReport))->toBeFalse()
        ->and($policy->publish($manager, $publishedReport))->toBeFalse();

    // Reopen: published or in-review can be reopened by admin/manager
    expect($policy->reopen($manager, $publishedReport))->toBeTrue()
        ->and($policy->reopen($admin, $publishedReport))->toBeTrue()
        ->and($policy->reopen($manager, $inReviewReport))->toBeTrue()
        ->and($policy->reopen($admin, $inReviewReport))->toBeTrue()
        ->and($policy->reopen($supervisor, $publishedReport))->toBeFalse()
        ->and($policy->reopen($supervisor, $inReviewReport))->toBeFalse()
        ->and($policy->reopen($manager, $draftReport))->toBeFalse();

    // Regenerate: published only, admin/manager only
    expect($policy->regenerate($manager, $publishedReport))->toBeTrue()
        ->and($policy->regenerate($admin, $publishedReport))->toBeTrue()
        ->and($policy->regenerate($supervisor, $publishedReport))->toBeFalse()
        ->and($policy->regenerate($manager, $inReviewReport))->toBeFalse()
        ->and($policy->regenerate($manager, $draftReport))->toBeFalse();

    // Archive: admin only, and only when transition is legal
    expect($policy->archive($admin, $publishedReport))->toBeTrue()
        ->and($policy->archive($admin, $archivedReport))->toBeFalse()
        ->and($policy->archive($manager, $publishedReport))->toBeFalse()
        ->and($policy->archive($supervisor, $publishedReport))->toBeFalse();
});

it('authorizes delete for admins and managers on any report, and supervisors on draft reports only', function (): void {
    $policy = app(SowReportPolicy::class);
    $market = Market::factory()->create();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);

    $otherManager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $otherManager->markets()->attach($market->id);

    $ownerSupervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $ownerSupervisor->markets()->attach($market->id);

    $otherSupervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $otherSupervisor->markets()->attach($market->id);

    $outOfMarketSupervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $otherMarket = Market::factory()->create();
    $outOfMarketSupervisor->markets()->attach($otherMarket->id);

    $managerDraft = SowReport::query()->create([
        'market_id' => $market->id,
        'created_by' => $manager->id,
        'title' => 'Manager Draft SOW',
        'status' => SowStatus::DRAFT,
    ]);

    $supervisorDraft = SowReport::query()->create([
        'market_id' => $market->id,
        'created_by' => $ownerSupervisor->id,
        'title' => 'Supervisor Draft SOW',
        'status' => SowStatus::DRAFT,
    ]);

    $inReviewBySupervisor = SowReport::query()->create([
        'market_id' => $market->id,
        'created_by' => $ownerSupervisor->id,
        'title' => 'In Review SOW',
        'status' => SowStatus::IN_REVIEW,
    ]);

    $publishedReport = SowReport::query()->create([
        'market_id' => $market->id,
        'created_by' => $manager->id,
        'title' => 'Manager Published SOW',
        'status' => SowStatus::PUBLISHED,
    ]);

    $archivedReport = SowReport::query()->create([
        'market_id' => $market->id,
        'created_by' => $manager->id,
        'title' => 'Archived SOW',
        'status' => SowStatus::ARCHIVED,
    ]);

    // Admin: can delete any SOW (draft, in-review, published, archived)
    expect($policy->delete($admin, $managerDraft))->toBeTrue()
        ->and($policy->delete($admin, $supervisorDraft))->toBeTrue()
        ->and($policy->delete($admin, $inReviewBySupervisor))->toBeTrue()
        ->and($policy->delete($admin, $publishedReport))->toBeTrue()
        ->and($policy->delete($admin, $archivedReport))->toBeTrue();

    // Manager: any report in assigned market (draft, in-review, published, archived)
    expect($policy->delete($manager, $managerDraft))->toBeTrue()
        ->and($policy->delete($manager, $supervisorDraft))->toBeTrue()
        ->and($policy->delete($manager, $inReviewBySupervisor))->toBeTrue()
        ->and($policy->delete($manager, $publishedReport))->toBeTrue()
        ->and($policy->delete($manager, $archivedReport))->toBeTrue();

    // Other manager: in same market can delete
    expect($policy->delete($otherManager, $inReviewBySupervisor))->toBeTrue()
        ->and($policy->delete($otherManager, $managerDraft))->toBeTrue();

    // Field supervisor: draft reports they own or are assigned to on the project
    expect($policy->delete($ownerSupervisor, $supervisorDraft))->toBeTrue()
        ->and($policy->delete($ownerSupervisor, $managerDraft))->toBeFalse()
        ->and($policy->delete($ownerSupervisor, $inReviewBySupervisor))->toBeFalse()
        ->and($policy->delete($ownerSupervisor, $publishedReport))->toBeFalse()
        ->and($policy->delete($ownerSupervisor, $archivedReport))->toBeFalse();

    // Colleague supervisor not assigned/owning cannot delete
    expect($policy->delete($otherSupervisor, $supervisorDraft))->toBeFalse()
        ->and($policy->delete($otherSupervisor, $inReviewBySupervisor))->toBeFalse()
        ->and($policy->delete($otherSupervisor, $publishedReport))->toBeFalse();

    // Out-of-market supervisor: cannot delete
    expect($policy->delete($outOfMarketSupervisor, $supervisorDraft))->toBeFalse();
});

it('denies view and delete to technicians and managers who are not assigned to the project', function (): void {
    $policy = app(SowReportPolicy::class);
    $market = Market::factory()->create();

    $assignedManager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $assignedManager->markets()->attach($market->id);

    $unassignedManager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $unassignedManager->markets()->attach($market->id);

    $assignedTech = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $assignedTech->markets()->attach($market->id);

    $unassignedTech = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $unassignedTech->markets()->attach($market->id);

    $project = Project::factory()->create([
        'market_id' => $market->id,
        'manager_id' => $assignedManager->id,
    ]);
    $project->fieldSupervisors()->attach($assignedTech->id);

    $projectReport = SowReport::query()->create([
        'market_id' => $market->id,
        'project_id' => $project->id,
        'created_by' => $assignedManager->id,
        'title' => 'Project SOW Report',
        'status' => SowStatus::DRAFT,
    ]);

    // Assigned manager has view and delete
    expect($policy->view($assignedManager, $projectReport))->toBeTrue()
        ->and($policy->delete($assignedManager, $projectReport))->toBeTrue();

    // A manager's draft is their working copy: the assigned technician cannot
    // see it until it is published, and therefore cannot delete it either.
    expect($policy->view($assignedTech, $projectReport))->toBeFalse()
        ->and($policy->delete($assignedTech, $projectReport))->toBeFalse();

    // Unassigned manager is denied view and delete
    expect($policy->view($unassignedManager, $projectReport))->toBeFalse()
        ->and($policy->delete($unassignedManager, $projectReport))->toBeFalse();

    // Unassigned technician is denied view and delete
    expect($policy->view($unassignedTech, $projectReport))->toBeFalse()
        ->and($policy->delete($unassignedTech, $projectReport))->toBeFalse();
});

it('authorizes unarchive for admins and in-market managers on archived reports only', function (): void {
    $policy = app(SowReportPolicy::class);
    $market = Market::factory()->create();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);

    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $supervisor->markets()->attach($market->id);

    $archivedReport = SowReport::query()->create([
        'market_id' => $market->id,
        'created_by' => $manager->id,
        'title' => 'Archived SOW',
        'status' => SowStatus::ARCHIVED,
    ]);

    $publishedReport = SowReport::query()->create([
        'market_id' => $market->id,
        'created_by' => $manager->id,
        'title' => 'Published SOW',
        'status' => SowStatus::PUBLISHED,
    ]);

    expect($policy->unarchive($admin, $archivedReport))->toBeTrue()
        ->and($policy->unarchive($manager, $archivedReport))->toBeTrue()
        ->and($policy->unarchive($supervisor, $archivedReport))->toBeFalse()
        ->and($policy->unarchive($admin, $publishedReport))->toBeFalse()
        ->and($policy->unarchive($manager, $publishedReport))->toBeFalse();
});

it('authorizes attaching a project for draft reports only', function (): void {
    $policy = app(SowReportPolicy::class);
    $market = Market::factory()->create();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);

    $ownerSupervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $ownerSupervisor->markets()->attach($market->id);

    $otherSupervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $otherSupervisor->markets()->attach($market->id);

    $draftReport = SowReport::query()->create([
        'market_id' => $market->id,
        'created_by' => $ownerSupervisor->id,
        'title' => 'Draft SOW',
        'status' => SowStatus::DRAFT,
    ]);

    $inReviewReport = SowReport::query()->create([
        'market_id' => $market->id,
        'created_by' => $ownerSupervisor->id,
        'title' => 'In Review SOW',
        'status' => SowStatus::IN_REVIEW,
    ]);

    expect($policy->attach($admin, $draftReport))->toBeTrue()
        ->and($policy->attach($manager, $draftReport))->toBeTrue()
        ->and($policy->attach($ownerSupervisor, $draftReport))->toBeTrue()
        ->and($policy->attach($otherSupervisor, $draftReport))->toBeFalse()
        ->and($policy->attach($admin, $inReviewReport))->toBeFalse()
        ->and($policy->attach($manager, $inReviewReport))->toBeFalse()
        ->and($policy->attach($ownerSupervisor, $inReviewReport))->toBeFalse();
});
