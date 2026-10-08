<?php

use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\ChangeOrders\Models\ChangeOrderPhoto;
use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Crew\Models\CrewAssignment;
use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\Markets\Models\Market;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\Projects\Enums\ProjectStatus;
use App\Domains\Projects\Events\ProjectStatusChanged;
use App\Domains\Projects\Models\IssueHistory;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Notifications\ProjectAssignedNotification;
use App\Domains\Projects\Notifications\ProjectCreatedNotification;
use App\Domains\ScopeOfWork\Enums\SowStatus;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\Templates\Models\Template;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('lists projects for admins with eager-loaded relations', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create();

    $this->actingAs($admin)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Index')
            ->has('projects.data', 1)
            ->has('projects.data.0.market')
            ->has('projects.data.0.manager')
            ->has('projects.data.0.field_supervisors_count'));
});

it('exposes assigned members with photo and role for the Assigned Members column', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();

    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $project = Project::factory()->create([
        'market_id' => $market->id,
        'manager_id' => $manager->id,
    ]);
    $project->fieldSupervisors()->attach($supervisor->id);

    $this->actingAs($admin)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Index')
            ->where('projects.data.0.manager.id', $manager->id)
            ->where('projects.data.0.manager.name', $manager->name)
            ->has('projects.data.0.manager.profile_photo_url')
            ->where('projects.data.0.manager.role_name', Role::MANAGER->value)
            ->has('projects.data.0.field_supervisors', 1)
            ->where('projects.data.0.field_supervisors.0.id', $supervisor->id)
            ->where('projects.data.0.field_supervisors.0.name', $supervisor->name)
            ->where('projects.data.0.field_supervisors.0.role_name', Role::FIELD_SUPERVISOR->value));
});

it('exposes assigned customers and customer details for the Customer column', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();
    $customerUser = User::factory()->create(['name' => 'Jane Customer'])->assignRole(Role::CUSTOMER->value);

    $project = Project::factory()->create([
        'market_id' => $market->id,
    ]);
    CustomerProjectAccess::factory()->create([
        'project_id' => $project->id,
        'user_id' => $customerUser->id,
    ]);

    $this->actingAs($admin)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Index')
            ->has('projects.data.0.customer_accesses', 1)
            ->where('projects.data.0.customer_accesses.0.user.id', $customerUser->id)
            ->where('projects.data.0.customer_accesses.0.user.name', 'Jane Customer')
            ->has('projects.data.0.customer_accesses.0.user.profile_photo_url'));
});

it('serializes every supervisor when a project has a large assignment', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);

    $project = Project::factory()->create([
        'market_id' => $market->id,
        'manager_id' => $manager->id,
    ]);

    // 20 assignees: the stack caps in the UI, but the payload must not truncate
    // so the "+N" chip can name everyone on hover.
    $supervisors = User::factory()->count(20)->create()
        ->each(fn (User $user) => $user->assignRole(Role::FIELD_SUPERVISOR->value));

    $project->fieldSupervisors()->attach($supervisors->pluck('id')->all());

    $this->actingAs($admin)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Index')
            ->has('projects.data.0.field_supervisors', 20)
            ->where('projects.data.0.field_supervisors.19.name', $supervisors->last()->name));
});

it('scopes the project list to the assigned manager', function (): void {
    $this->withoutVite();

    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $otherManager = User::factory()->create()->assignRole(Role::MANAGER->value);

    $assigned = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
    Project::factory()->create(['market_id' => $market->id, 'manager_id' => $otherManager->id]);

    $this->actingAs($manager)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Index')
            ->has('projects.data', 1)
            ->where('projects.data.0.id', $assigned->id));
});

it('scopes the project list to assigned field supervisors', function (): void {
    $this->withoutVite();

    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $assigned = Project::factory()->create();
    $other = Project::factory()->create();

    $assigned->fieldSupervisors()->attach($field->id);

    $this->actingAs($field)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Index')
            ->has('projects.data', 1)
            ->where('projects.data.0.id', $assigned->id));

    expect($other->id)->not->toBeNull();
});

it('creates a project as a pending record', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();

    $this->actingAs($admin)
        ->post(route('projects.store'), [
            'market_id' => $market->id,
            'name' => 'Lomas Peak Site',
            'address' => '123 Main St',
            'ps_number' => 'PS12345',
            'completed_date' => '2026-12-01',
            'awarded_date' => '2026-06-01',
            'rec_issued_date' => '2026-06-05',
            'vzw_engineer_name' => 'John Engineer',
            'vzw_engineer_email' => 'engineer@verizon.example',
            'vzw_engineer_phone' => '(555) 123-4567',
        ])
        ->assertRedirect(route('projects.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('projects', [
        'name' => 'Lomas Peak Site',
        'ps_number' => 'PS12345',
        'completed_date' => '2026-12-01 00:00:00',
        'awarded_date' => '2026-06-01 00:00:00',
        'rec_issued_date' => '2026-06-05 00:00:00',
        'market_id' => $market->id,
        'status' => ProjectStatus::PENDING->value,
        'progress_percentage' => 0,
        'created_by' => $admin->id,
    ]);
});

it('rejects project creation without the award and REC dates', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    $this->actingAs($admin)
        ->post(route('projects.store'), [
            'market_id' => Market::factory()->create()->id,
            'name' => 'No Timeline Site',
            'completed_date' => '2026-12-01',
        ])
        ->assertSessionHasErrors(['awarded_date', 'rec_issued_date']);
});

it('rejects a desired completion date that is already in the past', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    $this->actingAs($admin)
        ->post(route('projects.store'), [
            'market_id' => Market::factory()->create()->id,
            'name' => 'Late Deadline Site',
            'completed_date' => now()->subWeek()->toDateString(),
            'awarded_date' => '2026-06-01',
            'rec_issued_date' => '2026-06-05',
        ])
        ->assertSessionHasErrors('completed_date');
});

it('accepts planned future dates for construction start and site completion', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    // Planned works: construction starts next week, site completes after it.
    $this->actingAs($admin)
        ->post(route('projects.store'), [
            'market_id' => Market::factory()->create()->id,
            'name' => 'Planned Works Site',
            'address' => '123 Main St',
            'completed_date' => '2026-12-01',
            'awarded_date' => '2026-06-01',
            'rec_issued_date' => '2026-06-05',
            'construction_started_date' => now()->addWeek()->toDateString(),
            'site_completed_date' => now()->addDays(14)->toDateString(),
        ])
        ->assertRedirect(route('projects.index'))
        ->assertSessionHasNoErrors();
});

it('rejects an implausibly old construction start date', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    $this->actingAs($admin)
        ->post(route('projects.store'), [
            'market_id' => Market::factory()->create()->id,
            'name' => 'Ancient Works Site',
            'completed_date' => '2026-12-01',
            'awarded_date' => '2026-06-01',
            'rec_issued_date' => '2026-06-05',
            'construction_started_date' => '2010-05-05',
        ])
        ->assertSessionHasErrors('construction_started_date');
});

it('rejects an implausibly old award date but allows a planned future one', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    $this->actingAs($admin)
        ->post(route('projects.store'), [
            'market_id' => Market::factory()->create()->id,
            'name' => 'Ancient Award Site',
            'completed_date' => '2026-12-01',
            'awarded_date' => '2010-05-05',
            'rec_issued_date' => '2026-06-05',
        ])
        ->assertSessionHasErrors('awarded_date');

    // A not-yet-awarded project may legitimately carry next week's date.
    $this->actingAs($admin)
        ->post(route('projects.store'), [
            'market_id' => Market::factory()->create()->id,
            'name' => 'Pending Award Site',
            'address' => '123 Main St',
            'completed_date' => '2026-12-01',
            'awarded_date' => now()->addWeek()->toDateString(),
            'rec_issued_date' => now()->addWeek()->toDateString(),
        ])
        ->assertRedirect(route('projects.index'));
});

it('accepts a future award and REC date when editing a project', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create();

    $nextWeek = now()->addWeek()->toDateString();

    $this->actingAs($admin)
        ->put(route('projects.update', $project), [
            'name' => $project->name,
            'awarded_date' => $nextWeek,
            'rec_issued_date' => $nextWeek,
        ])
        ->assertRedirect(route('projects.show', $project))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('projects', [
        'id' => $project->id,
        'awarded_date' => $nextWeek.' 00:00:00',
        'rec_issued_date' => $nextWeek.' 00:00:00',
    ]);
});

it('rejects project creation without a name', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    $this->actingAs($admin)
        ->post(route('projects.store'), [
            'market_id' => Market::factory()->create()->id,
        ])
        ->assertSessionHasErrors('name');
});

it('rejects project creation without an address', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    // The address is the project-side fallback for the package cover and for
    // final-approval readiness, so it cannot be left blank at creation.
    $this->actingAs($admin)
        ->post(route('projects.store'), [
            'market_id' => Market::factory()->create()->id,
            'name' => 'Addressless Site',
            'completed_date' => '2026-12-01',
            'awarded_date' => '2026-06-01',
            'rec_issued_date' => '2026-06-05',
        ])
        ->assertSessionHasErrors('address');
});

it('rejects an update that blanks out the address', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create(['address' => '123 Main St']);

    $this->actingAs($admin)
        ->put(route('projects.update', $project), [
            'name' => $project->name,
            'address' => '',
        ])
        ->assertSessionHasErrors('address');

    expect($project->fresh()->address)->toBe('123 Main St');
});

it('allows an update that leaves the address out of the payload', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create(['address' => '123 Main St']);

    // Partial field-level updates (e.g. assigning a manager) must not be
    // blocked by a field the caller never touched.
    $this->actingAs($admin)
        ->put(route('projects.update', $project), [
            'name' => 'Renamed Without Address',
        ])
        ->assertSessionHasNoErrors();

    expect($project->fresh()->address)->toBe('123 Main St');
});

it('denies project creation to a manager without market access', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $market = Market::factory()->create();

    $this->actingAs($manager)
        ->post(route('projects.store'), [
            'market_id' => $market->id,
            'name' => 'Restricted Site',
            'completed_date' => '2026-12-01',
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');
});

it('allows project creation for a manager with market access', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $market = Market::factory()->create();
    $manager->markets()->attach($market->id);

    $this->actingAs($manager)
        ->post(route('projects.store'), [
            'market_id' => $market->id,
            'name' => 'Allowed Site',
            'address' => '123 Main St',
            'completed_date' => '2026-12-01',
            'awarded_date' => '2026-06-01',
            'rec_issued_date' => '2026-06-05',
            'vzw_engineer_name' => 'John Engineer',
            'vzw_engineer_email' => 'engineer@verizon.example',
            'vzw_engineer_phone' => '(555) 123-4567',
        ])
        ->assertRedirect(route('projects.index'));

    $this->assertDatabaseHas('projects', ['name' => 'Allowed Site']);
});

it('assigns the creating manager as the project manager without notifying them', function (): void {
    Notification::fake();

    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $market = Market::factory()->create();
    $manager->markets()->attach($market->id);

    $this->actingAs($manager)
        ->post(route('projects.store'), [
            'market_id' => $market->id,
            'name' => 'Manager Owned Site',
            'address' => '123 Main St',
            'completed_date' => '2026-12-01',
            'awarded_date' => '2026-06-01',
            'rec_issued_date' => '2026-06-05',
        ])
        ->assertRedirect(route('projects.index'));

    $this->assertDatabaseHas('projects', [
        'name' => 'Manager Owned Site',
        'manager_id' => $manager->id,
        'created_by' => $manager->id,
    ]);

    // The creating manager is the actor, so they are not notified about their
    // own project assignment.
    Notification::assertNotSentTo($manager, ProjectAssignedNotification::class);
});

it('lets a manager with market access open the create project page', function (): void {
    $this->withoutVite();

    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach(Market::factory()->create()->id);

    $this->actingAs($manager)
        ->get(route('projects.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Projects/Create'));
});

it('prevents a manager from reassigning the manager of their own project', function (): void {
    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $otherManager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    $otherManager->markets()->attach($market->id);

    $project = Project::factory()->create([
        'market_id' => $market->id,
        'manager_id' => $manager->id,
    ]);

    $this->actingAs($manager)
        ->put(route('projects.update', $project), [
            'name' => 'Renamed Site',
            'manager_id' => $otherManager->id,
        ])
        ->assertSessionHasErrors('manager_id');

    $this->assertDatabaseHas('projects', [
        'id' => $project->id,
        'name' => $project->name,
        'manager_id' => $manager->id,
    ]);
});

it('transitions the project out of pending when a supervisor starts working', function (): void {
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $market = Market::factory()->create();
    $template = Template::factory()->create(['market_id' => $market->id]);
    $project = Project::factory()->create(['market_id' => $market->id]);
    $project->fieldSupervisors()->attach($field->id);

    expect($project->status)->toBe(ProjectStatus::PENDING);

    $this->actingAs($field)
        ->post(route('packages.store', $project), [
            'project_id' => $project->id,
            'template_id' => $template->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    // Creating the first package must immediately move the project to
    // in_progress so the "What's happening" card stops saying pending.
    expect($project->fresh()->status)->toBe(ProjectStatus::IN_PROGRESS);
});

it('updates a project and fires a status change event', function (): void {
    Event::fake([ProjectStatusChanged::class]);

    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $market = Market::factory()->create();
    $manager->markets()->attach($market->id);
    $project = Project::factory()->create([
        'market_id' => $market->id,
        'manager_id' => $manager->id,
        'status' => ProjectStatus::PENDING->value,
    ]);

    $this->actingAs($manager)
        ->put(route('projects.update', $project), [
            'name' => 'Updated Site Name',
            'status' => ProjectStatus::IN_PROGRESS->value,
        ])
        ->assertRedirect(route('projects.show', $project))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('projects', [
        'id' => $project->id,
        'name' => 'Updated Site Name',
        'status' => ProjectStatus::IN_PROGRESS->value,
    ]);

    Event::assertDispatched(ProjectStatusChanged::class);
});

it('allows only the assigned manager to update a project', function (): void {
    $assignedManager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $otherManager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $market = Market::factory()->create();
    $assignedManager->markets()->attach($market->id);
    $otherManager->markets()->attach($market->id);

    $project = Project::factory()->create([
        'market_id' => $market->id,
        'manager_id' => $assignedManager->id,
    ]);

    $this->actingAs($otherManager)
        ->put(route('projects.update', $project), [
            'name' => 'Should Not Update',
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');
});

it('rejects deleting a project that has close-out packages', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();
    $project = Project::factory()->create(['market_id' => $market->id]);
    $template = Template::factory()->create(['market_id' => $market->id]);
    CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('projects.destroy', $project))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'deleted_at' => null]);
});

it('soft-deletes a project as admin', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create();

    $this->actingAs($admin)
        ->delete(route('projects.destroy', $project))
        ->assertRedirect(route('projects.index'))
        ->assertSessionHas('success');

    $this->assertSoftDeleted('projects', ['id' => $project->id]);
});

it('soft-deletes related change orders and issues when a project is deleted', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create();
    $changeOrder = ChangeOrder::factory()->create(['project_id' => $project->id, 'created_by' => $admin->id]);
    $issue = IssueHistory::factory()->create(['project_id' => $project->id, 'reported_by' => $admin->id]);

    $this->actingAs($admin)
        ->delete(route('projects.destroy', $project))
        ->assertRedirect(route('projects.index'))
        ->assertSessionHas('success');

    $this->assertSoftDeleted('projects', ['id' => $project->id]);
    $this->assertSoftDeleted('change_orders', ['id' => $changeOrder->id]);
    $this->assertDatabaseMissing('issue_histories', ['id' => $issue->id]);
});

it('soft-deletes the project SOWs and library media so they leave the lists', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();
    $project = Project::factory()->create(['market_id' => $market->id]);
    // A supervisor-authored SOW survives on /sow via `created_by` unless the row is deleted.
    $report = SowReport::create([
        'market_id' => $market->id,
        'created_by' => $admin->id,
        'project_id' => $project->id,
        'status' => SowStatus::DRAFT,
        'title' => 'Cascade SOW',
        'site_name' => 'Cascade Site',
        'revision' => 1,
        'revision_date' => now()->toDateString(),
    ]);
    $asset = MediaAsset::factory()->create(['project_id' => $project->id]);
    $assignment = CrewAssignment::create(['project_id' => $project->id, 'user_id' => $admin->id]);

    $this->actingAs($admin)
        ->delete(route('projects.destroy', $project))
        ->assertRedirect(route('projects.index'))
        ->assertSessionHas('success');

    $this->assertSoftDeleted('sow_reports', ['id' => $report->id]);
    $this->assertSoftDeleted('media_assets', ['id' => $asset->id]);
    $this->assertDatabaseMissing('crew_assignments', ['id' => $assignment->id]);
});

it('purges a project trashed packages so nothing restorable is left behind', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();
    $project = Project::factory()->create(['market_id' => $market->id]);
    $template = Template::factory()->create(['market_id' => $market->id]);

    // Already in the Deleted Packages list — only ACTIVE packages block deletion.
    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
    ]);
    $package->delete();

    $this->actingAs($admin)
        ->delete(route('projects.destroy', $project))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('closeout_packages', ['id' => $package->id]);
});

it('deletes change-order photo files from disk when the project goes', function (): void {
    Storage::fake('private');

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();
    $project = Project::factory()->create(['market_id' => $market->id]);
    $changeOrder = ChangeOrder::factory()->create(['project_id' => $project->id, 'created_by' => $admin->id]);

    $photo = ChangeOrderPhoto::create([
        'change_order_id' => $changeOrder->id,
        'uploaded_by' => $admin->id,
        'original_name' => 'before.jpg',
        'path' => 'change-orders/before.jpg',
        'mime_type' => 'image/jpeg',
        'size_bytes' => 1234,
    ]);
    Storage::disk('private')->put($photo->path, 'x');
    Storage::disk('private')->assertExists($photo->path);

    $this->actingAs($admin)
        ->delete(route('projects.destroy', $project))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('change_order_photos', ['id' => $photo->id]);
    Storage::disk('private')->assertMissing($photo->path);
});

it('allows an assigned manager to delete their project', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);

    $this->actingAs($manager)
        ->delete(route('projects.destroy', $project))
        ->assertRedirect(route('projects.index'))
        ->assertSessionHas('success');

    $this->assertSoftDeleted('projects', ['id' => $project->id]);
});

it('denies project deletion to managers who do not own it', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $otherManager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['manager_id' => $otherManager->id]);

    $this->actingAs($manager)
        ->delete(route('projects.destroy', $project))
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'deleted_at' => null]);
});

it('notifies admins when a project is created', function (): void {
    Notification::fake();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();

    $this->actingAs($admin)
        ->post(route('projects.store'), [
            'market_id' => $market->id,
            'name' => 'Notified Site',
            'address' => '123 Main St',
            'completed_date' => '2026-12-01',
            'awarded_date' => '2026-06-01',
            'rec_issued_date' => '2026-06-05',
            'vzw_engineer_name' => 'John Engineer',
            'vzw_engineer_email' => 'engineer@verizon.example',
            'vzw_engineer_phone' => '(555) 123-4567',
        ]);

    Notification::assertSentTo($admin, ProjectCreatedNotification::class);
});

it('rejects a manager without market access with a validation error instead of 403', function (): void {
    $this->withoutVite();

    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => null]);

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    $this->actingAs($admin)
        ->put(route('projects.update', $project), ['manager_id' => $manager->id])
        ->assertSessionHasErrors('manager_id');

    expect($project->fresh()->manager_id)->toBeNull();
});

it('searches projects by name', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    Project::factory()->create(['name' => 'Lomas Peak Alpha Site']);
    Project::factory()->create(['name' => 'Lomas Peak Beta Site']);
    Project::factory()->create(['name' => 'Ohio River Tower']);

    $this->actingAs($admin)
        ->get(route('projects.index', ['search' => 'Lomas Peak']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Index')
            ->has('projects.data', 2)
            ->where('projects.total', 2));
});

it('filters projects by status', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    Project::factory()->create(['status' => ProjectStatus::COMPLETED]);
    Project::factory()->create(['status' => ProjectStatus::PENDING]);

    $this->actingAs($admin)
        ->get(route('projects.index', ['status' => 'completed']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Index')
            ->has('projects.data', 1)
            ->where('projects.data.0.status', 'completed'));
});

it('filters projects by market', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $texas = Market::factory()->create(['name' => 'Texas']);
    $ohio = Market::factory()->create(['name' => 'Ohio']);
    Project::factory()->create(['market_id' => $texas->id, 'name' => 'Texas Site']);
    Project::factory()->create(['market_id' => $ohio->id, 'name' => 'Ohio Site']);

    $this->actingAs($admin)
        ->get(route('projects.index', ['market_id' => $texas->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Index')
            ->has('projects.data', 1)
            ->where('projects.data.0.name', 'Texas Site'));
});

it('offers only the markets present on the projects visible to the user in the market filter', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $texas = Market::factory()->create(['name' => 'Texas']);
    $ohio = Market::factory()->create(['name' => 'Ohio']);
    $unused = Market::factory()->create(['name' => 'Nowhere']);
    Project::factory()->create(['market_id' => $texas->id, 'name' => 'Texas Site']);
    Project::factory()->create(['market_id' => $ohio->id, 'name' => 'Ohio Site']);

    $this->actingAs($admin)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Index')
            ->has('markets', 2)
            ->where('markets.0.name', 'Ohio')
            ->where('markets.1.name', 'Texas')
            ->missing('markets.2'));
});

it('offers the project markets to a field supervisor who has no market assignment', function (): void {
    $this->withoutVite();

    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $ohio = Market::factory()->create(['name' => 'Ohio']);
    $project = Project::factory()->create(['market_id' => $ohio->id, 'name' => 'Ohio Site']);
    $project->fieldSupervisors()->attach($supervisor->id);

    $this->actingAs($supervisor)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Index')
            ->has('markets', 1)
            ->where('markets.0.name', 'Ohio'));
});

it('exposes the project creation permission computed by the policy', function (): void {
    $this->withoutVite();

    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);

    // A manager with no market assigned cannot create, even though the market
    // filter itself may still list markets from the projects they can see.
    $this->actingAs($manager)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Index')
            ->where('can_create_project', false));

    $manager->markets()->attach($market->id);

    $this->actingAs($manager)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Index')
            ->where('can_create_project', true));
});

it('exposes package status counts per project in the list', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create();
    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'status' => PackageStatus::SUBMITTED,
    ]);
    CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'status' => PackageStatus::DRAFT,
    ]);

    $this->actingAs($admin)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Index')
            ->has('projects.data', 1)
            ->where('projects.data.0.package_statuses.draft', 1)
            ->where('projects.data.0.package_statuses.submitted', 1)
            ->where('projects.data.0.package_statuses.under_review', 0)
            ->where('projects.data.0.package_statuses.approved', 0)
            ->where('projects.data.0.package_statuses.rejected', 0));
});

it('filters projects by package status', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $withSubmitted = Project::factory()->create(['name' => 'Needs Review']);
    CloseoutPackage::factory()->create([
        'project_id' => $withSubmitted->id,
        'status' => PackageStatus::SUBMITTED,
    ]);
    $draftOnly = Project::factory()->create(['name' => 'Draft Only']);
    CloseoutPackage::factory()->create([
        'project_id' => $draftOnly->id,
        'status' => PackageStatus::DRAFT,
    ]);

    $this->actingAs($admin)
        ->get(route('projects.index', ['package_status' => 'submitted']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Index')
            ->has('projects.data', 1)
            ->where('projects.data.0.id', $withSubmitted->id));
});

it('sorts projects by name ascending', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    Project::factory()->create(['name' => 'Zulu Site']);
    Project::factory()->create(['name' => 'Alpha Site']);

    $this->actingAs($admin)
        ->get(route('projects.index', ['sort' => 'name', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Index')
            ->has('projects.data', 2)
            ->where('projects.data.0.name', 'Alpha Site')
            ->where('projects.data.1.name', 'Zulu Site'));
});

it('sorts projects newest first by default and falls back safely on unknown sort', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $older = Project::factory()->create(['name' => 'Older Site']);
    $newer = Project::factory()->create(['name' => 'Newer Site']);

    $older->forceFill(['created_at' => now()->subDays(2)])->save();
    $newer->forceFill(['created_at' => now()])->save();

    $this->actingAs($admin)
        ->get(route('projects.index', ['sort' => 'malicious_column', 'direction' => 'desc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Index')
            ->has('projects.data', 2)
            ->where('projects.data.0.id', $newer->id));
});

it('exposes open issues count per project in the list', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $projectWithIssues = Project::factory()->create(['name' => 'Project with Issues']);
    $projectResolved = Project::factory()->create(['name' => 'Project Resolved']);

    IssueHistory::factory()->create([
        'project_id' => $projectWithIssues->id,
        'resolved_at' => null,
    ]);
    IssueHistory::factory()->create([
        'project_id' => $projectWithIssues->id,
        'resolved_at' => null,
    ]);
    IssueHistory::factory()->create([
        'project_id' => $projectResolved->id,
        'resolved_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Index')
            ->where('projects.data.0.open_issues_count', 2)
            ->where('projects.data.1.open_issues_count', 0));
});

it('allows updating a project without changing its name', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();
    $project = Project::factory()->create([
        'market_id' => $market->id,
        'name' => 'Existing Unique Site',
    ]);

    $this->actingAs($admin)
        ->put(route('projects.update', $project), [
            'name' => 'Existing Unique Site',
            'notes' => 'Updated notes without changing name',
        ])
        ->assertRedirect(route('projects.show', $project))
        ->assertSessionHas('success');

    expect($project->fresh()->notes)->toBe('Updated notes without changing name');
});

it('allows reusing a project name if the old project was soft-deleted', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();

    // 1. Create and soft-delete a project
    $deletedProject = Project::factory()->create([
        'market_id' => $market->id,
        'name' => 'Archived Tower Site',
    ]);
    $deletedProject->delete();

    // 2. Creating a new project with the same name must succeed
    $this->actingAs($admin)
        ->post(route('projects.store'), [
            'market_id' => $market->id,
            'name' => 'Archived Tower Site',
            'address' => '123 Main St',
            'completed_date' => '2026-12-01',
            'awarded_date' => '2026-06-01',
            'rec_issued_date' => '2026-06-05',
        ])
        ->assertRedirect(route('projects.index'))
        ->assertSessionHas('success');

    // 3. Updating an existing project to the soft-deleted name must also succeed
    $activeProject = Project::factory()->create([
        'market_id' => $market->id,
        'name' => 'Active Project To Rename',
    ]);

    $anotherDeleted = Project::factory()->create([
        'market_id' => $market->id,
        'name' => 'Another Deleted Site',
    ]);
    $anotherDeleted->delete();

    $this->actingAs($admin)
        ->put(route('projects.update', $activeProject), [
            'name' => 'Another Deleted Site',
        ])
        ->assertRedirect(route('projects.show', $activeProject))
        ->assertSessionHas('success');

    expect($activeProject->fresh()->name)->toBe('Another Deleted Site');
});

it('rejects duplicate project names within the same market for active projects', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();

    Project::factory()->create([
        'market_id' => $market->id,
        'name' => 'Downtown Cell Tower',
    ]);

    // Store rejection
    $this->actingAs($admin)
        ->post(route('projects.store'), [
            'market_id' => $market->id,
            'name' => 'Downtown Cell Tower',
            'completed_date' => '2026-12-01',
        ])
        ->assertSessionHasErrors('name');

    // Update rejection
    $otherProject = Project::factory()->create([
        'market_id' => $market->id,
        'name' => 'Uptown Cell Tower',
    ]);

    $this->actingAs($admin)
        ->put(route('projects.update', $otherProject), [
            'name' => 'Downtown Cell Tower',
        ])
        ->assertSessionHasErrors('name');
});

it('allows identical project names across different markets', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $marketA = Market::factory()->create(['name' => 'Market Ohio']);
    $marketB = Market::factory()->create(['name' => 'Market California']);

    Project::factory()->create([
        'market_id' => $marketA->id,
        'name' => 'Standard Tower Alpha',
    ]);

    $this->actingAs($admin)
        ->post(route('projects.store'), [
            'market_id' => $marketB->id,
            'name' => 'Standard Tower Alpha',
            'address' => '123 Main St',
            'completed_date' => '2026-12-01',
            'awarded_date' => '2026-06-01',
            'rec_issued_date' => '2026-06-05',
        ])
        ->assertRedirect(route('projects.index'))
        ->assertSessionHas('success');
});
