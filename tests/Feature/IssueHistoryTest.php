<?php

use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\Projects\Enums\IssueType;
use App\Domains\Projects\Models\IssueHistory;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('records an issue on a project', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create();

    $this->actingAs($admin)
        ->post(route('projects.issues.store', $project), [
            'type' => IssueType::LOCK->value,
            'description' => 'The site gate lock is broken.',
            'verizon_guidance' => 'Contact the site owner.',
        ])
        ->assertRedirect(route('projects.issues.index', $project))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('issue_histories', [
        'project_id' => $project->id,
        'reported_by' => $admin->id,
        'type' => IssueType::LOCK->value,
        'description' => 'The site gate lock is broken.',
        'verizon_guidance' => 'Contact the site owner.',
    ]);
});

it('rejects an issue with an invalid type', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create();

    $this->actingAs($admin)
        ->post(route('projects.issues.store', $project), [
            'type' => 'not_a_type',
            'description' => 'Invalid issue',
        ])
        ->assertSessionHasErrors('type');
});

it('allows assigned field supervisors to record issues', function (): void {
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create();
    $project->fieldSupervisors()->attach($field->id);

    $this->actingAs($field)
        ->post(route('projects.issues.store', $project), [
            'type' => IssueType::SITE_ACCESS->value,
            'description' => 'Could not access the site.',
        ])
        ->assertRedirect(route('projects.issues.index', $project));

    $this->assertDatabaseHas('issue_histories', [
        'project_id' => $project->id,
        'reported_by' => $field->id,
        'type' => IssueType::SITE_ACCESS->value,
    ]);
});

it('lists issues for authorized users', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create();
    $issue = IssueHistory::factory()->create(['project_id' => $project->id]);

    $this->actingAs($admin)
        ->get(route('projects.issues.index', $project))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Projects/Issues')
            ->has('issues', 1)
            ->where('issues.0.id', $issue->id));
});

it('resolves an issue as a manager', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create();
    $issue = IssueHistory::factory()->create(['project_id' => $project->id]);

    $this->actingAs($manager)
        ->put(route('projects.issues.resolve', [$project, $issue]))
        ->assertRedirect(route('projects.issues.index', $project))
        ->assertSessionHas('success');

    expect($issue->fresh()->resolved_at)->not->toBeNull();
});

it('denies issue resolution to field supervisors', function (): void {
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create();
    $issue = IssueHistory::factory()->create(['project_id' => $project->id]);

    $this->actingAs($field)
        ->put(route('projects.issues.resolve', [$project, $issue]))
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');
});

it('allows an admin to delete any issue and its comments', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $otherUser = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create();
    $issue = IssueHistory::factory()->create([
        'project_id' => $project->id,
        'reported_by' => $otherUser->id,
    ]);
    $issue->comments()->create([
        'user_id' => $otherUser->id,
        'body' => 'Test comment to be deleted',
    ]);

    $this->actingAs($admin)
        ->delete(route('projects.issues.destroy', [$project, $issue]))
        ->assertRedirect(route('projects.issues.index', $project))
        ->assertSessionHas('success', 'Issue deleted.');

    $this->assertDatabaseMissing('issue_histories', ['id' => $issue->id]);
    $this->assertDatabaseCount('issue_comments', 0);
});

it('allows a manager to delete their own issue but not anothers', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $other = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);

    $ownIssue = IssueHistory::factory()->create([
        'project_id' => $project->id,
        'reported_by' => $manager->id,
    ]);
    $otherIssue = IssueHistory::factory()->create([
        'project_id' => $project->id,
        'reported_by' => $other->id,
    ]);

    $this->actingAs($manager)
        ->delete(route('projects.issues.destroy', [$project, $ownIssue]))
        ->assertRedirect(route('projects.issues.index', $project))
        ->assertSessionHas('success', 'Issue deleted.');

    $this->assertDatabaseMissing('issue_histories', ['id' => $ownIssue->id]);

    $this->actingAs($manager)
        ->delete(route('projects.issues.destroy', [$project, $otherIssue]))
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');

    $this->assertDatabaseHas('issue_histories', ['id' => $otherIssue->id]);
});

it('denies issue deletion to field supervisors', function (): void {
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create();
    $project->fieldSupervisors()->attach($field->id);

    $issue = IssueHistory::factory()->create([
        'project_id' => $project->id,
        'reported_by' => $field->id,
    ]);

    $this->actingAs($field)
        ->delete(route('projects.issues.destroy', [$project, $issue]))
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');

    $this->assertDatabaseHas('issue_histories', ['id' => $issue->id]);
});

it('allows authorized customers to view issues for granted projects', function (): void {
    $this->withoutVite();

    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    $project = Project::factory()->create();
    $issue = IssueHistory::factory()->create(['project_id' => $project->id]);

    CustomerProjectAccess::factory()->create([
        'user_id' => $customer->id,
        'project_id' => $project->id,
    ]);

    $this->actingAs($customer)
        ->get(route('projects.issues.index', $project))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Projects/Issues')
            ->has('issues', 1)
            ->where('issues.0.id', $issue->id));
});

it('denies customers from viewing issues for ungranted projects', function (): void {
    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    $project = Project::factory()->create();

    $this->actingAs($customer)
        ->get(route('projects.issues.index', $project))
        ->assertForbidden();
});

it('accepts a problem description at exactly the 3000 character limit', function (): void {
    // The dialog's counter and this rule must agree on the boundary: the client
    // shows "N / 3000", so 3000 has to be a valid submission.
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create();

    $this->actingAs($admin)
        ->post(route('projects.issues.store', $project), [
            'type' => 'lock',
            'description' => str_repeat('a', 3000),
        ])
        ->assertSessionHasNoErrors();

    expect($project->issues()->firstOrFail()->description)->toHaveLength(3000);
});

it('rejects a problem description past the 3000 character limit', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create();

    $this->actingAs($admin)
        ->post(route('projects.issues.store', $project), [
            'type' => 'lock',
            'description' => str_repeat('a', 3001),
        ])
        ->assertSessionHasErrors('description');

    expect($project->issues()->exists())->toBeFalse();
});

it('measures the description limit in the characters the user typed', function (): void {
    // Browsers submit textarea newlines as CRLF, so prose with line breaks
    // arrives longer than the dialog's counter showed. 3,000 has to mean 3,000
    // visible characters, not 3,000 characters on the wire.
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create();

    // Mid-string, because TrimStrings would strip leading newlines. 1,496 + 4
    // line breaks + 1,500 = 3,000 as typed; the CRLF pair per break makes it
    // 3,004 on the wire.
    $description = str_repeat('a', 1496)."\r\n\r\n\r\n\r\n".str_repeat('a', 1500);

    $this->actingAs($admin)
        ->post(route('projects.issues.store', $project), [
            'type' => 'lock',
            'description' => $description,
        ])
        ->assertSessionHasNoErrors();

    $stored = $project->issues()->firstOrFail()->description;

    expect(mb_strlen($stored))->toBe(3000)
        ->and($stored)->not->toContain("\r")
        ->and($stored)->toBe(str_repeat('a', 1496)."\n\n\n\n".str_repeat('a', 1500));
});
