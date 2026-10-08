<?php

declare(strict_types=1);

use App\Domains\ChangeOrders\Actions\SendChangeOrderAction;
use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\ChangeOrders\Models\ChangeOrderItem;
use App\Domains\ChangeOrders\Models\ChangeOrderRecipient;
use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
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
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Queue::fake();

    $this->manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $this->project = Project::factory()->create(['manager_id' => $this->manager->id]);
    $this->customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    CustomerProjectAccess::factory()->create([
        'user_id' => $this->customer->id,
        'project_id' => $this->project->id,
    ]);

    $this->co = ChangeOrder::factory()->for($this->project)->create([
        'created_by' => $this->manager->id,
        'title' => 'Reinforcement',
    ]);
    ChangeOrderItem::factory()->create([
        'change_order_id' => $this->co->id,
        'is_included' => true,
    ]);
    ChangeOrderRecipient::factory()->create([
        'change_order_id' => $this->co->id,
        'user_id' => $this->customer->id,
    ]);
});

/**
 * A manager re-sending a change order whose email already went out. The first
 * send left a SENT row carrying the deterministic per-recipient key; the second
 * must record a *fresh* intent so the email can actually be delivered again,
 * without mutating the original audit row.
 */
it('records a fresh intent when re-sending a change order whose email already went out', function (): void {
    $action = app(SendChangeOrderAction::class);

    // First send → READY intent.
    $action->execute($this->co);
    expect(EmailLog::query()->count())->toBe(1);

    // Manager releases it from the outbox; it goes out.
    $first = EmailLog::query()->sole();
    $first->update([
        'status' => EmailStatus::SENT->value,
        'sent_at' => now()->subDay(),
        'provider_message_id' => 'mx-first',
    ]);

    $originalKey = $first->idempotency_key;
    $originalSentAt = $first->sent_at;

    // Second send — this is the "Resend to Customer" click.
    $action->execute($this->co);

    // A second row now exists and is releasable.
    expect(EmailLog::query()->count())->toBe(2);

    $fresh = EmailLog::query()
        ->where('id', '!=', $first->id)
        ->sole();

    expect($fresh->status)->toBe(EmailStatus::READY)
        ->and($fresh->recipient_email)->toBe($this->customer->email)
        ->and($fresh->type)->toBe(EmailType::CHANGE_ORDER);

    // The fresh row must NOT collide with the original's unique key.
    expect($fresh->idempotency_key)->not->toBe($originalKey);

    // Original audit row is untouched.
    $first->refresh();
    expect($first->status)->toBe(EmailStatus::SENT)
        ->and($first->sent_at?->timestamp)->toBe($originalSentAt?->timestamp)
        ->and($first->provider_message_id)->toBe('mx-first');

    // Key still fits the varchar(64) column.
    expect(strlen((string) $fresh->idempotency_key))->toBeLessThanOrEqual(64);
});

it('stays idempotent while the original intent is still undelivered', function (): void {
    $action = app(SendChangeOrderAction::class);

    $action->execute($this->co);
    expect(EmailLog::query()->count())->toBe(1);

    // Still READY (nobody released it) — re-running send must not pile up rows.
    $action->execute($this->co);

    expect(EmailLog::query()->count())->toBe(1);
});

it('records a fresh intent after a FAILED delivery so the customer still gets it', function (): void {
    $action = app(SendChangeOrderAction::class);

    $action->execute($this->co);

    EmailLog::query()->sole()->update([
        'status' => EmailStatus::FAILED->value,
        'failed_reason' => 'SMTP timeout',
    ]);

    $action->execute($this->co);

    expect(EmailLog::query()->count())->toBe(2)
        ->and(EmailLog::query()->where('status', EmailStatus::READY->value)->count())->toBe(1);
});

it('keeps idempotency for a PENDING send already in flight', function (): void {
    $action = app(SendChangeOrderAction::class);

    $action->execute($this->co);
    EmailLog::query()->sole()->update(['status' => EmailStatus::PENDING->value]);

    $action->execute($this->co);

    expect(EmailLog::query()->count())->toBe(1);
});

it('records a fresh intent after a CANCELLED send', function (): void {
    $action = app(SendChangeOrderAction::class);

    $action->execute($this->co);
    EmailLog::query()->sole()->update([
        'status' => EmailStatus::CANCELLED->value,
        'cancelled_at' => now(),
    ]);

    $action->execute($this->co);

    expect(EmailLog::query()->count())->toBe(2);
});

it('mints a distinct key on every repeated resend', function (): void {
    $action = app(SendChangeOrderAction::class);

    $keys = [];

    for ($i = 0; $i < 4; $i++) {
        $action->execute($this->co);

        // Release whatever this pass just recorded.
        EmailLog::query()
            ->where('status', EmailStatus::READY->value)
            ->update(['status' => EmailStatus::SENT->value, 'sent_at' => now()]);

        $keys[] = EmailLog::query()->latest('id')->value('idempotency_key');
    }

    expect($keys)->toHaveCount(4);
    expect(array_unique($keys))->toHaveCount(4);
    expect(EmailLog::query()->count())->toBe(4);
});
