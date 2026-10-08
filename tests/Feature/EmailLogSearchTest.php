<?php

use App\Domains\Email\Models\EmailLog;
use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('filters email logs by search across recipient, sender and subject', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create();

    EmailLog::factory()->create([
        'project_id' => $project->id,
        'recipient_email' => 'alice@example.com',
        'recipient_name' => 'Alice Wonder',
        'sender_email' => 'noreply@example.com',
        'subject' => 'Stage upload complete',
        'sent_at' => now()->subDay(),
    ]);

    EmailLog::factory()->create([
        'project_id' => $project->id,
        'recipient_email' => 'bob@example.com',
        'sender_email' => 'manager@example.com',
        'sender_name' => 'Bob Builder',
        'subject' => 'Final PDF ready',
        'sent_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.email-logs.index', ['search' => 'alice']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/EmailLogs/Index')
            ->has('logs', 1)
            ->where('logs.0.recipient_email', 'alice@example.com'));

    $this->actingAs($admin)
        ->get(route('admin.email-logs.index', ['search' => 'Bob']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('logs', 1)
            ->where('logs.0.recipient_email', 'bob@example.com'));

    $this->actingAs($admin)
        ->get(route('admin.email-logs.index', ['search' => 'Final PDF']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('logs', 1));
});

it('filters email logs by an inclusive date range', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create();

    EmailLog::factory()->create([
        'project_id' => $project->id,
        'recipient_email' => 'old@example.com',
        'sent_at' => now()->subDays(3),
    ]);

    EmailLog::factory()->create([
        'project_id' => $project->id,
        'recipient_email' => 'recent@example.com',
        'sent_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.email-logs.index', [
            'date_from' => now()->startOfDay()->toDateString(),
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('logs', 1)
            ->where('logs.0.recipient_email', 'recent@example.com'));

    $this->actingAs($admin)
        ->get(route('admin.email-logs.index', [
            'date_to' => now()->subDays(2)->toDateString(),
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('logs', 1)
            ->where('logs.0.recipient_email', 'old@example.com'));
});

it('sorts email logs oldest first and defaults to newest first', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = Project::factory()->create();

    $old = EmailLog::factory()->create([
        'project_id' => $project->id,
        'sent_at' => now()->subDays(5),
    ]);

    $new = EmailLog::factory()->create([
        'project_id' => $project->id,
        'sent_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.email-logs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('logs.0.id', $new->id)
            ->where('logs.1.id', $old->id));

    $this->actingAs($admin)
        ->get(route('admin.email-logs.index', ['sort' => 'oldest']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('logs.0.id', $old->id)
            ->where('logs.1.id', $new->id));
});

it('rejects an unknown sort value', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    $this->actingAs($admin)
        ->get(route('admin.email-logs.index', ['sort' => 'bogus']))
        ->assertRedirect()
        ->assertSessionHasErrors('sort');
});

it('applies search within the manager scoped logs', function (): void {
    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);

    $ownProject = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
    $otherManager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $otherProject = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $otherManager->id]);

    EmailLog::factory()->create([
        'project_id' => $ownProject->id,
        'recipient_email' => 'shared@example.com',
    ]);

    EmailLog::factory()->create([
        'project_id' => $otherProject->id,
        'recipient_email' => 'shared@example.com',
    ]);

    $this->actingAs($manager)
        ->get(route('manager.email-logs.manager', ['search' => 'shared']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Manager/EmailLogs/Index')
            ->has('logs', 1)
            ->where('logs.0.project.id', $ownProject->id));
});

it('paginates email logs and echoes the applied filters', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    EmailLog::factory()->count(60)->create();

    $this->actingAs($admin)
        ->get(route('admin.email-logs.index', ['sort' => 'oldest']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('logs', 50)
            ->where('pagination.current_page', 1)
            ->where('pagination.last_page', 2)
            ->where('pagination.total', 60)
            ->where('pagination.per_page', 50)
            ->where('filters.sort', 'oldest'));
});
