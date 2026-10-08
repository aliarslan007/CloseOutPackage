<?php

use App\Domains\Projects\Models\IssueComment;
use App\Domains\Projects\Models\IssueHistory;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Notifications\IssueCommentedNotification;
use App\Domains\Projects\Notifications\IssueOpenedNotification;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('allows an admin to comment on an issue', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create();
    $issue = IssueHistory::factory()->create(['project_id' => $project->id]);

    $this->actingAs($admin)
        ->post(route('projects.issues.comments.store', [$project, $issue]), [
            'body' => 'Arranging a key now.',
        ])
        ->assertRedirect(route('projects.issues.index', $project))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('issue_comments', [
        'issue_history_id' => $issue->id,
        'user_id' => $admin->id,
        'body' => 'Arranging a key now.',
    ]);
});

it('requires a comment body', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create();
    $issue = IssueHistory::factory()->create(['project_id' => $project->id]);

    $this->actingAs($admin)
        ->post(route('projects.issues.comments.store', [$project, $issue]), ['body' => ''])
        ->assertSessionHasErrors('body');
});

it('allows an assigned field supervisor to comment', function (): void {
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create();
    $project->fieldSupervisors()->attach($field->id);
    $issue = IssueHistory::factory()->create(['project_id' => $project->id]);

    $this->actingAs($field)
        ->post(route('projects.issues.comments.store', [$project, $issue]), [
            'body' => 'Gate is now open.',
        ])
        ->assertRedirect(route('projects.issues.index', $project));
});

it('denies commenting to a supervisor not assigned to the project', function (): void {
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create();
    $issue = IssueHistory::factory()->create(['project_id' => $project->id]);

    $this->actingAs($field)
        ->post(route('projects.issues.comments.store', [$project, $issue]), [
            'body' => 'Should be denied.',
        ])
        ->assertSessionHas('error');
});

it('denies commenting to a manager not assigned to the project', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create();
    $issue = IssueHistory::factory()->create(['project_id' => $project->id]);

    $this->actingAs($manager)
        ->post(route('projects.issues.comments.store', [$project, $issue]), [
            'body' => 'Should be denied.',
        ])
        ->assertSessionHas('error');
});

it('lists issues with their comments and reporters', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $reporter = User::factory()->create(['name' => 'John Smith'])->assignRole(Role::FIELD_SUPERVISOR->value);
    $commenter = User::factory()->create(['name' => 'Sarah Jones'])->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create();
    $project->fieldSupervisors()->attach($reporter->id);
    $issue = IssueHistory::factory()->create(['project_id' => $project->id, 'reported_by' => $reporter->id]);
    $comment = IssueComment::factory()->create([
        'issue_history_id' => $issue->id,
        'user_id' => $commenter->id,
        'body' => 'ETA 2 hours.',
    ]);

    $this->actingAs($admin)
        ->get(route('projects.issues.index', $project))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Projects/Issues')
            ->where('issues.0.reporter.name', 'John Smith')
            ->where('issues.0.comments.0.author.name', 'Sarah Jones')
            ->where('issues.0.comments.0.body', 'ETA 2 hours.'));
});

it('notifies admins and the project manager when an issue is opened', function (): void {
    Notification::fake();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $reporter = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $project->fieldSupervisors()->attach($reporter->id);

    $this->actingAs($reporter)
        ->post(route('projects.issues.store', $project), [
            'type' => 'lock',
            'description' => 'Gate locked.',
        ]);

    Notification::assertSentTo($admin, IssueOpenedNotification::class);
    Notification::assertSentTo($manager, IssueOpenedNotification::class);
    Notification::assertNotSentTo($reporter, IssueOpenedNotification::class);
});

it('notifies project members on a new comment, excluding the commenter', function (): void {
    Notification::fake();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $project->fieldSupervisors()->attach($supervisor->id);
    $issue = IssueHistory::factory()->create(['project_id' => $project->id]);

    $this->actingAs($supervisor)
        ->post(route('projects.issues.comments.store', [$project, $issue]), [
            'body' => 'On site now.',
        ]);

    Notification::assertSentTo($admin, IssueCommentedNotification::class);
    Notification::assertSentTo($manager, IssueCommentedNotification::class);
    Notification::assertNotSentTo($supervisor, IssueCommentedNotification::class);
});

it('denies commenting on a resolved issue', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create();
    $issue = IssueHistory::factory()->create([
        'project_id' => $project->id,
        'resolved_at' => now(),
    ]);

    $this->actingAs($admin)
        ->post(route('projects.issues.comments.store', [$project, $issue]), [
            'body' => 'Trying to comment on resolved issue.',
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');

    $this->assertDatabaseCount('issue_comments', 0);
});
