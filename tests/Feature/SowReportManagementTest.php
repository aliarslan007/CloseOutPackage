<?php

declare(strict_types=1);

use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Models\Project;
use App\Domains\ScopeOfWork\Enums\SowStatus;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('allows a field technician without market_user row to access create sow page', function (): void {
    $this->withoutVite();

    $market = Market::factory()->create(['name' => 'Ohio Market', 'is_active' => true]);
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    // Explicitly verify supervisor has NO direct market_user records
    expect($supervisor->markets()->count())->toBe(0);

    $this->actingAs($supervisor)
        ->get(route('sow.reports.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ScopeOfWork/Create')
            ->has('markets', 1)
            ->where('markets.0.id', $market->id));
});

it('populates assigned projects for field supervisor on create page', function (): void {
    $this->withoutVite();

    $market = Market::factory()->create(['is_active' => true]);
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $assignedProject = Project::factory()->create(['market_id' => $market->id, 'name' => 'Assigned Project']);
    $assignedProject->fieldSupervisors()->attach($supervisor->id);

    $unassignedProject = Project::factory()->create(['market_id' => $market->id, 'name' => 'Unassigned Project']);

    $this->actingAs($supervisor)
        ->get(route('sow.reports.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ScopeOfWork/Create')
            ->has('projects', 1)
            ->where('projects.0.id', $assignedProject->id));
});

it('allows a field technician to store a new SOW report', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $response = $this->actingAs($supervisor)
        ->post(route('sow.reports.store'), [
            'market_id' => $market->id,
            'title' => 'Field Tech SOW Report',
            'site_name' => 'Test Cell Tower',
        ]);

    $report = SowReport::where('title', 'Field Tech SOW Report')->first();
    expect($report)->not->toBeNull()
        ->and($report->created_by)->toBe($supervisor->id)
        ->and($report->status)->toBe(SowStatus::DRAFT);

    $response->assertRedirect(route('sow.reports.show', $report));
});

it('denies customer access to create sow page', function (): void {
    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);

    $this->actingAs($customer)
        ->get(route('sow.reports.create'))
        ->assertForbidden();
});

it('rejects store payloads that exceed the tightened field limits', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->actingAs($supervisor)
        ->post(route('sow.reports.store'), [
            'market_id' => $market->id,
            'title' => str_repeat('a', 121),
            'site_name' => str_repeat('b', 121),
            'site_address' => str_repeat('c', 251),
            'project_description' => str_repeat('d', 201),
            'scope_of_work_description' => str_repeat('e', 351),
            'site_access_notes' => str_repeat('f', 2501),
            'demarcation' => str_repeat('g', 101),
            'ps_number' => str_repeat('h', 21),
        ])
        ->assertSessionHasErrors([
            'title',
            'site_name',
            'site_address',
            'project_description',
            'scope_of_work_description',
            'site_access_notes',
            'demarcation',
            'ps_number',
        ]);

    expect(SowReport::count())->toBe(0);
});

it('rejects update payloads that exceed the tightened field limits', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $supervisor->markets()->attach($market->id);

    $report = SowReport::query()->create([
        'market_id' => $market->id,
        'created_by' => $supervisor->id,
        'title' => 'Limit Check SOW',
        'status' => SowStatus::DRAFT,
    ]);

    $this->actingAs($supervisor)
        ->put(route('sow.reports.update', $report), [
            'site_name' => str_repeat('b', 121),
            'project_description' => str_repeat('d', 201),
            'demarcation' => str_repeat('g', 101),
            'ps_number' => str_repeat('h', 21),
        ])
        ->assertSessionHasErrors(['site_name', 'project_description', 'demarcation', 'ps_number']);
});

it('accepts title and site_name at 120, project_description at 200, and ps_number at 20 character limits', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $supervisor->markets()->attach($market->id);

    $report = SowReport::query()->create([
        'market_id' => $market->id,
        'created_by' => $supervisor->id,
        'title' => str_repeat('t', 120),
        'status' => SowStatus::DRAFT,
    ]);

    $this->actingAs($supervisor)
        ->put(route('sow.reports.update', $report), [
            'site_name' => str_repeat('s', 120),
            'project_description' => str_repeat('p', 200),
            'ps_number' => str_repeat('n', 20),
        ])
        ->assertSessionHasNoErrors();

    expect($report->fresh()->site_name)->toBe(str_repeat('s', 120))
        ->and($report->fresh()->project_description)->toBe(str_repeat('p', 200))
        ->and($report->fresh()->ps_number)->toBe(str_repeat('n', 20));
});

it('accepts a demarcation at the 100 character limit', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $supervisor->markets()->attach($market->id);

    $report = SowReport::query()->create([
        'market_id' => $market->id,
        'created_by' => $supervisor->id,
        'title' => 'Boundary Check SOW',
        'status' => SowStatus::DRAFT,
    ]);

    $demarcation = str_repeat('x', 100);

    $this->actingAs($supervisor)
        ->put(route('sow.reports.update', $report), ['demarcation' => $demarcation])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($report->fresh()->demarcation)->toBe($demarcation);
});

it('redirects unauthenticated guests from sow routes', function (): void {
    $response = $this->get(route('sow.reports.create'));
    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/session-expired');
});

it('renders sow reports index page without lazy loading violations', function (): void {
    $this->withoutVite();

    $market = Market::factory()->create(['is_active' => true]);
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $creator = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    SowReport::create([
        'market_id' => $market->id,
        'created_by' => $creator->id,
        'title' => 'Cell Site Survey',
        'site_name' => 'Site Alpha',
        'status' => SowStatus::DRAFT,
        'revision' => 1,
        'revision_date' => now()->toDateString(),
    ]);

    $this->actingAs($admin)
        ->get(route('sow.reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ScopeOfWork/Index')
            ->has('reports', 1)
            ->where('reports.0.title', 'Cell Site Survey')
            ->where('reports.0.creator.id', $creator->id));
});

it('allows a manager to publish a draft SOW report directly', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);

    $report = SowReport::create([
        'market_id' => $market->id,
        'created_by' => $manager->id,
        'title' => 'Manager Direct SOW',
        'site_name' => 'Site Direct Finalize',
        'status' => SowStatus::DRAFT,
        'revision' => 1,
        'revision_date' => now()->toDateString(),
    ]);

    $this->actingAs($manager)
        ->post(route('sow.reports.publish', $report))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($report->fresh()->status)->toBe(SowStatus::PUBLISHED)
        ->and($report->fresh()->published_by)->toBe($manager->id);
});

it('denies a field supervisor from directly publishing an SOW report', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $report = SowReport::create([
        'market_id' => $market->id,
        'created_by' => $supervisor->id,
        'title' => 'Supervisor SOW',
        'site_name' => 'Site Alpha',
        'status' => SowStatus::DRAFT,
        'revision' => 1,
        'revision_date' => now()->toDateString(),
    ]);

    $this->actingAs($supervisor)
        ->post(route('sow.reports.publish', $report))
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');

    expect($report->fresh()->status)->toBe(SowStatus::DRAFT);
});

it('enforces read-only mode for managers on supervisor draft until submitted for review', function (): void {
    $this->withoutVite();

    $market = Market::factory()->create(['is_active' => true]);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);

    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $supervisor->markets()->attach($market->id);

    $report = SowReport::create([
        'market_id' => $market->id,
        'created_by' => $supervisor->id,
        'title' => 'Supervisor Draft In Progress',
        'site_name' => 'Site Cooking',
        'status' => SowStatus::DRAFT,
        'revision' => 1,
        'revision_date' => now()->toDateString(),
    ]);

    // 1. Manager views the supervisor's draft -> read-only: cannot update, cannot submit, cannot publish
    $showResponse = $this->actingAs($manager)->get(route('sow.reports.show', $report));
    $showResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ScopeOfWork/Show')
            ->where('can.update', false)
            ->where('can.submit', false)
            ->where('can.publish', false));

    // Manager cannot update project/survey data while in supervisor draft
    $this->actingAs($manager)
        ->put(route('sow.reports.update', $report), ['site_name' => 'Manager Attempted Change'])
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');

    // Manager cannot publish supervisor draft directly
    $this->actingAs($manager)
        ->post(route('sow.reports.publish', $report))
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');

    // 2. Supervisor submits the report for review
    $this->actingAs($supervisor)
        ->post(route('sow.reports.submit', $report))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($report->fresh()->status)->toBe(SowStatus::IN_REVIEW);

    // 3. Now manager can view with update and publish abilities enabled
    $inReviewShow = $this->actingAs($manager)->get(route('sow.reports.show', $report));
    $inReviewShow->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ScopeOfWork/Show')
            ->where('can.update', true)
            ->where('can.submit', false)
            ->where('can.publish', true));

    // Manager can now adjust data
    $this->actingAs($manager)
        ->put(route('sow.reports.update', $report), ['site_name' => 'Manager Adjusted Site'])
        ->assertRedirect()
        ->assertSessionHas('success');

    // Manager can now approve & publish
    $this->actingAs($manager)
        ->post(route('sow.reports.publish', $report))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($report->fresh()->status)->toBe(SowStatus::PUBLISHED)
        ->and($report->fresh()->site_name)->toBe('Manager Adjusted Site');
});

it('persists complete survey data in wizard store and formats date as Y-m-d', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $payload = [
        'market_id' => $market->id,
        'title' => 'SOW With Full Survey Data',
        'site_name' => 'Cell Site 101',
        'report_date' => '2026-09-10',
        'field_survey_date' => '2026-09-09',
        'fiber_provider' => 'VZB',
        'provider_contact_name' => 'Ahmed Khan',
        'provider_contact_phone' => '+92-300-1234567',
        'provider_contact_email' => 'ahmed.khan@fiberprovider.pk',
        'demarcation' => 'Equipment Shelter Rack #4',
        'ps_number' => 'PS-203822',
        'survey_contractor_name' => 'Precision Survey',
        'survey_contact_name' => 'Sarah Jenkins',
        'survey_contact_phone' => '+1-555-0199',
        'survey_contact_email' => 's.jenkins@precisionsurvey.com',
        'scope_of_work_description' => 'Detailed scope of work description text.',
        'site_access_notes' => 'Site gate code 4589.',
    ];

    $response = $this->actingAs($supervisor)
        ->post(route('sow.reports.store'), $payload);

    $report = SowReport::query()->where('title', 'SOW With Full Survey Data')->first();
    expect($report)->not->toBeNull()
        ->and($report->field_survey_date?->format('Y-m-d'))->toBe('2026-09-09')
        ->and($report->provider_contact_name)->toBe('Ahmed Khan')
        ->and($report->demarcation)->toBe('Equipment Shelter Rack #4')
        ->and($report->ps_number)->toBe('PS-203822')
        ->and($report->toArray()['field_survey_date'])->toBe('2026-09-09')
        ->and($report->toArray()['report_date'])->toBe('2026-09-10');

    $response->assertRedirect(route('sow.reports.show', $report));

    $showResponse = $this->actingAs($supervisor)->get(route('sow.reports.show', $report));
    $showResponse->assertOk();
    $showReportProp = $showResponse->original->getData()['page']['props']['report'];
    expect($showReportProp['field_survey_date'])->toBe('2026-09-09')
        ->and($showReportProp['provider_contact_name'])->toBe('Ahmed Khan');
});

it('persists survey data updates and preserves Y-m-d date format', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $supervisor->markets()->attach($market->id);

    $report = SowReport::query()->create([
        'market_id' => $market->id,
        'created_by' => $supervisor->id,
        'title' => 'Initial Survey SOW',
        'site_name' => 'Site Initial',
        'status' => SowStatus::DRAFT,
        'revision' => 1,
        'revision_date' => '2026-09-10',
    ]);

    $updatePayload = [
        'field_survey_date' => '2026-09-09',
        'fiber_provider' => 'VZB',
        'provider_contact_name' => 'Ahmed Khan Updated',
        'provider_contact_phone' => '+92-300-9999999',
        'provider_contact_email' => 'ahmed.updated@fiberprovider.pk',
        'demarcation' => 'Updated Demarcation Point',
        'ps_number' => 'PS-999999',
        'survey_contractor_name' => 'New Contractor',
        'survey_contact_name' => 'John Doe',
        'survey_contact_phone' => '+1-555-9999',
        'survey_contact_email' => 'j.doe@contractor.com',
        'scope_of_work_description' => 'Updated scope of work description.',
        'site_access_notes' => 'Updated gate code 9999.',
    ];

    $this->actingAs($supervisor)
        ->put(route('sow.reports.update', $report), $updatePayload)
        ->assertRedirect()
        ->assertSessionHas('success');

    $fresh = $report->fresh();
    expect($fresh->field_survey_date?->format('Y-m-d'))->toBe('2026-09-09')
        ->and($fresh->provider_contact_name)->toBe('Ahmed Khan Updated')
        ->and($fresh->demarcation)->toBe('Updated Demarcation Point')
        ->and($fresh->toArray()['field_survey_date'])->toBe('2026-09-09');
});

it('allows admin and manager to delete a published SOW report', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);

    $publishedReportByManager = SowReport::query()->create([
        'market_id' => $market->id,
        'created_by' => $manager->id,
        'title' => 'Published SOW by Manager',
        'status' => SowStatus::PUBLISHED,
        'published_at' => now(),
    ]);

    $publishedReportByAdmin = SowReport::query()->create([
        'market_id' => $market->id,
        'created_by' => $admin->id,
        'title' => 'Published SOW by Admin',
        'status' => SowStatus::PUBLISHED,
        'published_at' => now(),
    ]);

    // Manager can delete published SOW report in their market
    $this->actingAs($manager)
        ->delete(route('sow.reports.destroy', $publishedReportByManager))
        ->assertRedirect(route('sow.reports.index'))
        ->assertSessionHas('success');

    expect($publishedReportByManager->fresh()->trashed())->toBeTrue();

    // Admin can delete published SOW report
    $this->actingAs($admin)
        ->delete(route('sow.reports.destroy', $publishedReportByAdmin))
        ->assertRedirect(route('sow.reports.index'))
        ->assertSessionHas('success');

    expect($publishedReportByAdmin->fresh()->trashed())->toBeTrue();
});

it('allows admin and manager to unarchive an archived SOW report', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);

    $archivedPublishedReport = SowReport::query()->create([
        'market_id' => $market->id,
        'created_by' => $manager->id,
        'title' => 'Archived Published SOW',
        'status' => SowStatus::ARCHIVED,
        'published_at' => now()->subDay(),
    ]);

    // Unarchive returns it to published
    $this->actingAs($manager)
        ->post(route('sow.reports.unarchive', $archivedPublishedReport))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($archivedPublishedReport->fresh()->status)->toBe(SowStatus::PUBLISHED);

    // Archive it again
    $this->actingAs($admin)
        ->post(route('sow.reports.archive', $archivedPublishedReport))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($archivedPublishedReport->fresh()->status)->toBe(SowStatus::ARCHIVED);

    // Admin unarchives
    $this->actingAs($admin)
        ->post(route('sow.reports.unarchive', $archivedPublishedReport))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($archivedPublishedReport->fresh()->status)->toBe(SowStatus::PUBLISHED);
});

it('allows field supervisor to delete draft SOW reports but forbids deleting in-review or published', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $supervisor->markets()->attach($market->id);

    $draftReport = SowReport::query()->create([
        'market_id' => $market->id,
        'created_by' => $supervisor->id,
        'title' => 'Supervisor Draft SOW',
        'status' => SowStatus::DRAFT,
    ]);

    $inReviewReport = SowReport::query()->create([
        'market_id' => $market->id,
        'created_by' => $supervisor->id,
        'title' => 'Supervisor In-Review SOW',
        'status' => SowStatus::IN_REVIEW,
    ]);

    $publishedReport = SowReport::query()->create([
        'market_id' => $market->id,
        'created_by' => $supervisor->id,
        'title' => 'Supervisor Published SOW',
        'status' => SowStatus::PUBLISHED,
        'published_at' => now(),
    ]);

    // Field supervisor CAN delete draft
    $this->actingAs($supervisor)
        ->delete(route('sow.reports.destroy', $draftReport))
        ->assertRedirect(route('sow.reports.index'))
        ->assertSessionHas('success');

    expect($draftReport->fresh()->trashed())->toBeTrue();

    // Field supervisor CANNOT delete in-review
    $this->actingAs($supervisor)
        ->delete(route('sow.reports.destroy', $inReviewReport))
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');

    expect($inReviewReport->fresh()->trashed())->toBeFalse();

    // Field supervisor CANNOT delete published
    $this->actingAs($supervisor)
        ->delete(route('sow.reports.destroy', $publishedReport))
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');

    expect($publishedReport->fresh()->trashed())->toBeFalse();
});

it('refuses to let a field supervisor delete a draft they did not create', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $manager->markets()->attach($market->id);
    $supervisor->markets()->attach($market->id);

    // The technician is assigned to the project, so the report is fully visible
    // and in-market for them — only ownership may withhold the delete.
    $project = Project::factory()->create([
        'market_id' => $market->id,
        'manager_id' => $manager->id,
    ]);
    $project->fieldSupervisors()->attach($supervisor->id);

    $managerDraft = SowReport::query()->create([
        'market_id' => $market->id,
        'project_id' => $project->id,
        'created_by' => $manager->id,
        'title' => 'Manager Draft SOW',
        'status' => SowStatus::DRAFT,
    ]);

    $adminDraft = SowReport::query()->create([
        'market_id' => $market->id,
        'project_id' => $project->id,
        'created_by' => $admin->id,
        'title' => 'Admin Draft SOW',
        'status' => SowStatus::DRAFT,
    ]);

    $this->actingAs($supervisor)
        ->delete(route('sow.reports.destroy', $managerDraft))
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');

    $this->actingAs($supervisor)
        ->delete(route('sow.reports.destroy', $adminDraft))
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');

    expect($managerDraft->fresh()->trashed())->toBeFalse()
        ->and($adminDraft->fresh()->trashed())->toBeFalse();
});

it('refuses a supervisor a manager draft, but keeps their own draft editable', function (): void {
    $this->withoutVite();

    $market = Market::factory()->create(['is_active' => true]);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $manager->markets()->attach($market->id);
    $supervisor->markets()->attach($market->id);

    $project = Project::factory()->create([
        'market_id' => $market->id,
        'manager_id' => $manager->id,
    ]);
    $project->fieldSupervisors()->attach($supervisor->id);

    $managerDraft = SowReport::query()->create([
        'market_id' => $market->id,
        'project_id' => $project->id,
        'created_by' => $manager->id,
        'title' => 'Manager Draft SOW',
        'status' => SowStatus::DRAFT,
    ]);

    $supervisorDraft = SowReport::query()->create([
        'market_id' => $market->id,
        'project_id' => $project->id,
        'created_by' => $supervisor->id,
        'title' => 'Supervisor Draft SOW',
        'status' => SowStatus::DRAFT,
    ]);

    // The manager's draft is not merely read-only for a supervisor — it is
    // theirs, so it must not be reachable at all.
    $this->actingAs($supervisor)
        ->get(route('sow.reports.show', $managerDraft))
        ->assertForbidden();

    $this->actingAs($supervisor)
        ->get(route('sow.reports.show', $supervisorDraft))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can.delete', true));

    // Managers and admins keep the delete action on every report.
    $this->actingAs($manager)
        ->get(route('sow.reports.show', $supervisorDraft))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can.delete', true));
});

it('does not display project SOW reports to technicians or managers not assigned to that project', function (): void {
    $market = Market::factory()->create(['is_active' => true]);

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
        'title' => 'Assigned Project SOW',
        'status' => SowStatus::DRAFT,
    ]);

    // Assigned manager sees the report
    $this->actingAs($assignedManager)
        ->get(route('sow.reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ScopeOfWork/Index')
            ->has('reports', 1)
        );

    // Assigned technician does NOT see the manager's draft: it is the
    // manager's working copy until it is published.
    $this->actingAs($assignedTech)
        ->get(route('sow.reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ScopeOfWork/Index')
            ->has('reports', 0)
        );

    // Unassigned manager does NOT see the report
    $this->actingAs($unassignedManager)
        ->get(route('sow.reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ScopeOfWork/Index')
            ->has('reports', 0)
        );

    // Unassigned technician does NOT see the report
    $this->actingAs($unassignedTech)
        ->get(route('sow.reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ScopeOfWork/Index')
            ->has('reports', 0)
        );
});

it('shows a technician their own draft and published SOWs, but never a manager or admin draft', function (): void {
    $market = Market::factory()->create(['is_active' => true]);

    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $tech = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $tech->markets()->attach($market->id);

    $project = Project::factory()->create([
        'market_id' => $market->id,
        'manager_id' => $manager->id,
    ]);
    $project->fieldSupervisors()->attach($tech->id);

    $techOwnDraft = SowReport::query()->create([
        'market_id' => $market->id,
        'project_id' => $project->id,
        'created_by' => $tech->id,
        'title' => 'My own draft',
        'status' => SowStatus::DRAFT,
    ]);
    $managerDraft = SowReport::query()->create([
        'market_id' => $market->id,
        'project_id' => $project->id,
        'created_by' => $manager->id,
        'title' => 'Manager working copy',
        'status' => SowStatus::DRAFT,
    ]);
    $adminDraft = SowReport::query()->create([
        'market_id' => $market->id,
        'project_id' => $project->id,
        'created_by' => $admin->id,
        'title' => 'Admin working copy',
        'status' => SowStatus::DRAFT,
    ]);
    $published = SowReport::query()->create([
        'market_id' => $market->id,
        'project_id' => $project->id,
        'created_by' => $manager->id,
        'title' => 'Approved and published',
        'status' => SowStatus::PUBLISHED,
    ]);

    $ids = collect($this->actingAs($tech)
        ->get(route('sow.reports.index'))
        ->assertOk()
        ->viewData('page')['props']['reports'])->pluck('id')->all();

    expect($ids)->toContain($techOwnDraft->id, $published->id)
        ->and($ids)->not->toContain($managerDraft->id, $adminDraft->id);

    // The manager and admin still see their own working copies.
    $managerIds = collect($this->actingAs($manager)
        ->get(route('sow.reports.index'))
        ->assertOk()
        ->viewData('page')['props']['reports'])->pluck('id')->all();

    expect($managerIds)->toContain($techOwnDraft->id, $managerDraft->id, $published->id);
});

it('lets a technician open their own draft but refuses a manager draft by id', function (): void {
    $market = Market::factory()->create(['is_active' => true]);

    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    $tech = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $tech->markets()->attach($market->id);

    $project = Project::factory()->create([
        'market_id' => $market->id,
        'manager_id' => $manager->id,
    ]);
    $project->fieldSupervisors()->attach($tech->id);

    $ownDraft = SowReport::query()->create([
        'market_id' => $market->id,
        'project_id' => $project->id,
        'created_by' => $tech->id,
        'title' => 'My own draft',
        'status' => SowStatus::DRAFT,
    ]);
    $managerDraft = SowReport::query()->create([
        'market_id' => $market->id,
        'project_id' => $project->id,
        'created_by' => $manager->id,
        'title' => 'Manager working copy',
        'status' => SowStatus::DRAFT,
    ]);

    $this->actingAs($tech)
        ->get(route('sow.reports.show', $ownDraft))
        ->assertOk();

    // Hidden from the list, and unreachable by direct URL too.
    $this->actingAs($tech)
        ->get(route('sow.reports.show', $managerDraft))
        ->assertForbidden();
});
