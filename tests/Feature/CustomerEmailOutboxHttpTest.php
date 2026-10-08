<?php

use App\Domains\Email\Enums\EmailStatus;
use App\Domains\Email\Enums\EmailType;
use App\Domains\Email\Models\EmailLog;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    config()->set('cop.customer_emails_enabled', true);
    Queue::fake();
});

function outboxProject(User $manager): Project
{
    return Project::factory()->create([
        'manager_id' => $manager->id,
    ]);
}

function outboxIntent(Project $project, array $overrides = []): EmailLog
{
    return EmailLog::factory()->ready()->create(array_merge([
        'project_id' => $project->id,
        'type' => EmailType::FINAL_PDF_EMAIL,
        'status' => EmailStatus::READY,
    ], $overrides));
}

it('lists the outbox for managers and admins', function (): void {
    // Page component arrives in Phase 7; withoutVite keeps the manifest out of it.
    $this->withoutVite();

    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = outboxProject($manager);
    outboxIntent($project);

    $this->actingAs($manager)
        ->get(route('manager.customer-emails.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('CustomerEmails/Index')->has('logs', 1));

    $this->actingAs($admin)
        ->get(route('manager.customer-emails.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('CustomerEmails/Index')->has('logs', 1));
});

it('hides rows outside a manager scope and blocks acting on them (no IDOR)', function (): void {
    $this->withoutVite();

    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $foreignManager = User::factory()->create()->assignRole(Role::MANAGER->value);

    $own = outboxIntent(outboxProject($manager));
    $foreign = outboxIntent(outboxProject($foreignManager));

    $this->actingAs($manager)
        ->get(route('manager.customer-emails.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('CustomerEmails/Index')->has('logs', 1));

    // Attempting to release another market's row is denied by the policy;
    // for browser requests that renders as redirect-back with an error.
    $this->actingAs($manager)
        ->post(route('manager.customer-emails.send', $foreign))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($foreign->refresh()->status)->toBe(EmailStatus::READY);

    // Own row releases fine.
    $this->actingAs($manager)
        ->post(route('manager.customer-emails.send', $own))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($own->refresh()->status)->toBe(EmailStatus::PENDING);
});

it('allows the admin to release any row', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    $foreign = outboxIntent(outboxProject($manager));

    $this->actingAs($admin)
        ->post(route('manager.customer-emails.send', $foreign))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($foreign->refresh()->status)->toBe(EmailStatus::PENDING);
});

it('validates the schedule payload server-side', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $intent = outboxIntent(outboxProject($manager));

    // Missing scheduled_at.
    $this->actingAs($manager)
        ->post(route('manager.customer-emails.schedule', $intent), [])
        ->assertSessionHasErrors(['scheduled_at']);

    // Past time is rejected by the request validation...
    $this->actingAs($manager)
        ->post(route('manager.customer-emails.schedule', $intent), [
            'scheduled_at' => now()->subMinutes(5)->toIso8601String(),
        ])
        ->assertSessionHasErrors(['scheduled_at']);

    expect($intent->refresh()->status)->toBe(EmailStatus::READY);

    // ...and a future time schedules it.
    $this->actingAs($manager)
        ->post(route('manager.customer-emails.schedule', $intent), [
            'scheduled_at' => now()->addHour()->toIso8601String(),
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($intent->refresh()->status)->toBe(EmailStatus::SCHEDULED)
        ->and($intent->scheduled_at)->not->toBeNull();
});

it('denies field supervisors the outbox entirely', function (): void {
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->actingAs($field)
        ->get(route('manager.customer-emails.index'))
        ->assertForbidden();
});

it('cancels a scheduled row through the endpoint', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $intent = outboxIntent(outboxProject($manager), ['status' => EmailStatus::SCHEDULED, 'scheduled_at' => now()->addHour()]);

    $this->actingAs($manager)
        ->post(route('manager.customer-emails.cancel', $intent))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($intent->refresh()->status)->toBe(EmailStatus::CANCELLED)
        ->and($intent->cancelled_at)->not->toBeNull();
});

it('flashes error when trying to send while customer emails are globally disabled', function (): void {
    config()->set('cop.customer_emails_enabled', false);

    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $intent = outboxIntent(outboxProject($manager));

    $this->actingAs($manager)
        ->post(route('manager.customer-emails.send', $intent))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($intent->refresh()->status)->toBe(EmailStatus::READY);
});
