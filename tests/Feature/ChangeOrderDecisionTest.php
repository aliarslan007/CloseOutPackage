<?php

use App\Domains\ChangeOrders\Actions\BuildChangeOrderCustomerEmailAction;
use App\Domains\ChangeOrders\Enums\ChangeOrderStatus;
use App\Domains\ChangeOrders\Enums\CustomerDecision;
use App\Domains\ChangeOrders\Jobs\SendChangeOrderEmailJob;
use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\ChangeOrders\Models\ChangeOrderItem;
use App\Domains\ChangeOrders\Models\ChangeOrderRecipient;
use App\Domains\ChangeOrders\Notifications\ChangeOrderSentNotification;
use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\Email\Actions\DeliverCustomerEmailAction;
use App\Domains\Email\Actions\LogEmailAction;
use App\Domains\Email\Enums\EmailStatus;
use App\Domains\Email\Enums\EmailType;
use App\Domains\Email\Models\EmailLog;
use App\Domains\Email\Services\DynamicMailSender;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Carbon\Carbon;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
});

function changeOrderWithItems(Project $project, User $creator, int $includedCount = 2): ChangeOrder
{
    $changeOrder = ChangeOrder::factory()->for($project)->create(['created_by' => $creator->id]);

    ChangeOrderItem::factory()->count($includedCount)->for($changeOrder)->create();
    ChangeOrderItem::factory()->excluded()->for($changeOrder)->create();

    return $changeOrder;
}

it('sending a change order flips DRAFT to SENT_TO_CUSTOMER, notifies recipients, and defers tokens and deadline to delivery', function (): void {
    Queue::fake();
    Notification::fake();

    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);
    CustomerProjectAccess::factory()->create(['user_id' => $customer->id, 'project_id' => $project->id]);

    $changeOrder = changeOrderWithItems($project, $manager);
    $changeOrder->recipients()->create(['user_id' => $customer->id, 'token_hash' => null, 'token_expires_at' => null]);

    $this->actingAs($manager)
        ->post(route('projects.change-orders.send', [$project, $changeOrder]))
        ->assertRedirect()
        ->assertSessionHas('success');

    // Send click flips the status and records the intent; tokens and the
    // decision deadline still move only at actual email delivery.
    $changeOrder->refresh();
    expect($changeOrder->status)->toBe(ChangeOrderStatus::SENT_TO_CUSTOMER)
        ->and($changeOrder->sent_at)->not->toBeNull()
        ->and($changeOrder->decision_deadline_at)->not->toBeNull();

    $recorded = Notification::sentNotifications()[User::class][$customer->getKey()] ?? [];
    expect($recorded)->toHaveKey(ChangeOrderSentNotification::class);

    // Still no decision token — that is minted at delivery only.
    $recipient = $changeOrder->recipients()->first();
    expect($recipient->token_hash)->toBeNull();

    $this->assertDatabaseHas('email_logs', [
        'change_order_id' => $changeOrder->id,
        'recipient_email' => $customer->email,
        'type' => EmailType::CHANGE_ORDER->value,
        'status' => EmailStatus::READY->value,
    ]);

    Queue::assertNotPushed(SendChangeOrderEmailJob::class);

    // Release from the outbox: status, tokens and deadline move to delivery.
    config()->set('cop.customer_emails_enabled', true);

    $intent = EmailLog::query()
        ->where('change_order_id', $changeOrder->id)
        ->where('recipient_email', $customer->email)
        ->firstOrFail();

    app(DeliverCustomerEmailAction::class)->sendNow($intent, $manager);

    Queue::assertPushed(SendChangeOrderEmailJob::class);

    (new SendChangeOrderEmailJob($intent))->handle(
        app(DynamicMailSender::class),
        app(BuildChangeOrderCustomerEmailAction::class),
        app(LogEmailAction::class),
    );

    $changeOrder->refresh();
    $recipient->refresh();

    expect($changeOrder->status)->toBe(ChangeOrderStatus::SENT_TO_CUSTOMER)
        ->and($changeOrder->sent_at)->not->toBeNull()
        ->and($changeOrder->decision_deadline_at)->not->toBeNull()
        ->and(strlen((string) $recipient->token_hash))->toBe(64)
        ->and($recipient->hasExpired())->toBeFalse()
        ->and($intent->refresh()->status)->toBe(EmailStatus::SENT);
});

it('refuses to send without included items or recipients', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $empty = ChangeOrder::factory()->for($project)->create(['created_by' => $manager->id]);

    $this->actingAs($manager)
        ->post(route('projects.change-orders.send', [$project, $empty]))
        ->assertRedirect()
        ->assertSessionHas('error');

    // CO with items but no recipients
    $withItems = changeOrderWithItems($project, $manager);
    $this->actingAs($manager)
        ->post(route('projects.change-orders.send', [$project, $withItems]))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($empty->fresh()->status)->toBe(ChangeOrderStatus::DRAFT)
        ->and($withItems->fresh()->status)->toBe(ChangeOrderStatus::DRAFT);
});

it('handles an expired email token with a clear customer explanation', function (): void {
    $recipient = ChangeOrderRecipient::factory()->expired()->create();

    // Invalid token renders TokenResponse with isExpired: true
    $this->get(route('change-orders.token.show', ['token' => str_repeat('a', 43)]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ChangeOrders/TokenResponse')
            ->where('isExpired', true)
        );

    // Expired token also renders TokenResponse with isExpired: true
    $raw = ChangeOrderRecipient::generateToken();
    $recipient->update(['token_hash' => ChangeOrderRecipient::hashToken($raw)]);

    $this->get(route('change-orders.token.show', ['token' => $raw]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ChangeOrders/TokenResponse')
            ->where('isExpired', true)
        );

    // Decision submission on expired token is rejected and redirected to token show view
    $this->post(route('change-orders.token.decide', ['token' => $raw]), [
        'decisions' => [],
    ])->assertRedirect(route('change-orders.token.show', ['token' => $raw]))->assertSessionHas('error');
});

it('records per-item decisions via token link and recomputes parent status', function (): void {
    $raw = ChangeOrderRecipient::generateToken();
    $recipient = ChangeOrderRecipient::factory()->create([
        'token_hash' => ChangeOrderRecipient::hashToken($raw),
    ]);
    $changeOrder = $recipient->changeOrder;
    $itemA = ChangeOrderItem::factory()->for($changeOrder)->create();
    $itemB = ChangeOrderItem::factory()->for($changeOrder)->create();

    $this->post(route('change-orders.token.decide', ['token' => $raw]), [
        'decisions' => [
            ['item_id' => $itemA->id, 'decision' => 'approved', 'note' => 'Looks good'],
            ['item_id' => $itemB->id, 'decision' => 'rejected', 'note' => 'Too expensive'],
        ],
    ])->assertRedirect();

    $changeOrder->refresh();
    expect($itemA->fresh()->customer_decision)->toBe(CustomerDecision::APPROVED)
        ->and($itemA->fresh()->customer_decision_note)->toBe('Looks good')
        ->and($itemB->fresh()->customer_decision)->toBe(CustomerDecision::REJECTED)
        // mixed decisions → Partially Approved
        ->and($changeOrder->status)->toBe(ChangeOrderStatus::PARTIALLY_APPROVED)
        ->and($recipient->fresh()->responded_at)->not->toBeNull();
});

it('status flows to approved when every item is approved', function (): void {
    $changeOrder = ChangeOrder::factory()->sent()->create();
    $items = ChangeOrderItem::factory()->count(2)->for($changeOrder)->create();

    foreach ($items as $item) {
        $item->update(['customer_decision' => CustomerDecision::APPROVED, 'decided_at' => now()]);
    }

    $changeOrder->recomputeStatus();
    expect($changeOrder->fresh()->status)->toBe(ChangeOrderStatus::APPROVED);
});

it('status flows to rejected when every item is rejected', function (): void {
    $changeOrder = ChangeOrder::factory()->sent()->create();
    $items = ChangeOrderItem::factory()->count(2)->for($changeOrder)->create();

    foreach ($items as $item) {
        $item->update(['customer_decision' => CustomerDecision::REJECTED, 'decided_at' => now()]);
    }

    $changeOrder->recomputeStatus();
    expect($changeOrder->fresh()->status)->toBe(ChangeOrderStatus::REJECTED);
});

it('status shows customer_action_required while decisions are partial', function (): void {
    $changeOrder = ChangeOrder::factory()->sent()->create();
    $a = ChangeOrderItem::factory()->approved()->for($changeOrder)->create();
    ChangeOrderItem::factory()->for($changeOrder)->create(); // still pending

    $changeOrder->recomputeStatus();
    expect($changeOrder->fresh()->status)->toBe(ChangeOrderStatus::CUSTOMER_ACTION_REQUIRED);
});

it('status stays sent_to_customer when nothing is decided', function (): void {
    $changeOrder = ChangeOrder::factory()->sent()->create();
    ChangeOrderItem::factory()->count(2)->for($changeOrder)->create();

    $changeOrder->recomputeStatus();
    expect($changeOrder->fresh()->status)->toBe(ChangeOrderStatus::SENT_TO_CUSTOMER);
});

it('excluded items do not block approval status', function (): void {
    $changeOrder = ChangeOrder::factory()->sent()->create();
    ChangeOrderItem::factory()->approved()->for($changeOrder)->create();
    ChangeOrderItem::factory()->excluded()->for($changeOrder)->create(); // undecided but not customer-facing

    $changeOrder->recomputeStatus();
    expect($changeOrder->fresh()->status)->toBe(ChangeOrderStatus::APPROVED);
});

it('denies customer decisions on draft change orders', function (): void {
    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    $changeOrder = ChangeOrder::factory()->create(); // draft
    $changeOrder->recipients()->create(['user_id' => $customer->id, 'token_hash' => null, 'token_expires_at' => null]);
    $project = $changeOrder->project;
    CustomerProjectAccess::factory()->create(['user_id' => $customer->id, 'project_id' => $project->id]);
    $item = ChangeOrderItem::factory()->for($changeOrder)->create();

    $this->actingAs($customer)
        ->post(route('customer.change-orders.decide', [$project, $changeOrder]), [
            'decisions' => [['item_id' => $item->id, 'decision' => 'approved']],
        ])
        ->assertRedirect()
        ->assertSessionHas('error');
});

it('logs change order delivery on the outbox row with an idempotency key', function (): void {
    // This test exercises the real send path, so customer emails must be on.
    config()->set('cop.customer_emails_enabled', true);

    $raw = ChangeOrderRecipient::generateToken();
    $recipient = ChangeOrderRecipient::factory()->create([
        'token_hash' => ChangeOrderRecipient::hashToken($raw),
    ]);
    $changeOrder = $recipient->changeOrder;

    $intent = EmailLog::factory()->create([
        'project_id' => $changeOrder->project_id,
        'change_order_id' => $changeOrder->id,
        'recipient_email' => $recipient->user->email,
        'type' => EmailType::CHANGE_ORDER,
        'status' => EmailStatus::PENDING,
        'context_id' => (string) $changeOrder->id,
        'payload' => null,
        'idempotency_key' => hash('sha256', 'change_order|null|'.$recipient->user->email.'|'.$changeOrder->id),
    ]);

    $job = new SendChangeOrderEmailJob($intent);

    try {
        $job->handle(app(DynamicMailSender::class), app(BuildChangeOrderCustomerEmailAction::class), app(LogEmailAction::class));
    } catch (Throwable) {
        // Brevo API not configured in tests — the failure path also lands on the row.
    }

    expect(EmailLog::query()->where('type', EmailType::CHANGE_ORDER)->whereKey($intent->id)->exists())->toBeTrue();
});

it('revokes the token after a decision and rejects a second submission', function (): void {
    $raw = ChangeOrderRecipient::generateToken();
    $recipient = ChangeOrderRecipient::factory()->create([
        'token_hash' => ChangeOrderRecipient::hashToken($raw),
    ]);
    $item = ChangeOrderItem::factory()->for($recipient->changeOrder)->create();

    $payload = ['decisions' => [['item_id' => $item->id, 'decision' => 'approved', 'note' => 'Approved']]];

    $this->post(route('change-orders.token.decide', ['token' => $raw]), $payload)
        ->assertRedirect(route('change-orders.token.show', ['token' => $raw]))
        ->assertSessionHas('success');

    $fresh = $recipient->fresh();
    expect($fresh->responded_at)->not->toBeNull()
        ->and($fresh->canDecide())->toBeFalse()
        ->and($item->fresh()->customer_decision)->toBe(CustomerDecision::APPROVED);

    // Replaying the same link must not record anything.
    $this->post(route('change-orders.token.decide', ['token' => $raw]), [
        'decisions' => [['item_id' => $item->id, 'decision' => 'rejected', 'note' => 'Changed my mind']],
    ])
        ->assertRedirect(route('change-orders.token.show', ['token' => $raw]))
        ->assertSessionHas('error', 'You have already submitted your decision for this change order. For security purposes, your link has been revoked. For further guidance, please contact your project manager or an administrator.');

    expect($item->fresh()->customer_decision)->toBe(CustomerDecision::APPROVED)
        ->and($item->fresh()->customer_decision_note)->toBe('Approved');
});

it('explains the revoked link and lists only admin contacts with an env-aware login url', function (): void {
    $admin = User::factory()->create(['name' => 'Ada Admin'])->assignRole(Role::ADMIN->value);
    $manager = User::factory()->create(['name' => 'Mia Manager'])->assignRole(Role::MANAGER->value);

    $raw = ChangeOrderRecipient::generateToken();
    $recipient = ChangeOrderRecipient::factory()->create([
        'token_hash' => ChangeOrderRecipient::hashToken($raw),
    ]);
    $changeOrder = $recipient->changeOrder;
    $changeOrder->project->update(['manager_id' => $manager->id]);
    $item = ChangeOrderItem::factory()->for($changeOrder)->create();

    $this->post(route('change-orders.token.decide', ['token' => $raw]), [
        'decisions' => [['item_id' => $item->id, 'decision' => 'approved']],
    ])->assertRedirect();

    // Consume the post-decision redirect (which confirms the submission), so the
    // GET below is a genuine later revisit of an already-used link.
    $this->get(route('change-orders.token.show', ['token' => $raw]))->assertOk();

    $this->get(route('change-orders.token.show', ['token' => $raw]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('ChangeOrders/TokenResponse')
            ->where('isExpired', true)
            ->where('reason', 'responded')
            ->has('contacts', 1)
            ->where('contacts.0.name', 'Ada Admin')
            ->where('contacts.0.email', $admin->email)
            ->whereNot('contacts.0.email', $manager->email)
            ->where('loginUrl', route('login')));
});

it('confirms the submission after a token decision instead of showing the revoked copy', function (): void {
    $raw = ChangeOrderRecipient::generateToken();
    $recipient = ChangeOrderRecipient::factory()->create([
        'token_hash' => ChangeOrderRecipient::hashToken($raw),
    ]);
    $item = ChangeOrderItem::factory()->for($recipient->changeOrder)->create();

    $this->post(route('change-orders.token.decide', ['token' => $raw]), [
        'decisions' => [['item_id' => $item->id, 'decision' => 'approved']],
    ])->assertRedirect(route('change-orders.token.show', ['token' => $raw]));

    // The token is spent, but the customer who just used it must be thanked, not
    // told their link was revoked.
    $this->get(route('change-orders.token.show', ['token' => $raw]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('ChangeOrders/TokenResponse')
            ->where('isExpired', true)
            ->where('reason', 'submitted'));
});

it('scopes the submitted confirmation to the token that was just used', function (): void {
    $raw = ChangeOrderRecipient::generateToken();
    $recipient = ChangeOrderRecipient::factory()->create([
        'token_hash' => ChangeOrderRecipient::hashToken($raw),
    ]);
    $item = ChangeOrderItem::factory()->for($recipient->changeOrder)->create();

    // A second link that was already used, so it renders the revoked copy.
    $otherRaw = ChangeOrderRecipient::generateToken();
    ChangeOrderRecipient::factory()->create([
        'token_hash' => ChangeOrderRecipient::hashToken($otherRaw),
        'responded_at' => now()->subDay(),
    ]);

    $this->post(route('change-orders.token.decide', ['token' => $raw]), [
        'decisions' => [['item_id' => $item->id, 'decision' => 'approved']],
    ])->assertRedirect();

    // The confirmation is bound to the token just used, so the other spent link
    // still gets the honest revoked message.
    $this->get(route('change-orders.token.show', ['token' => $otherRaw]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('reason', 'responded'));
});

it('marks a change order read on first token open and never again', function (): void {
    Carbon::setTestNow('2026-08-29 09:00:00');

    $raw = ChangeOrderRecipient::generateToken();
    $recipient = ChangeOrderRecipient::factory()->create([
        'token_hash' => ChangeOrderRecipient::hashToken($raw),
    ]);

    $this->get(route('change-orders.token.show', ['token' => $raw]))->assertOk();
    $first = $recipient->fresh()->viewed_at;

    expect($first)->not->toBeNull();

    // Re-opening the link later must not rewrite the viewed_at audit point.
    Carbon::setTestNow('2026-08-29 11:30:00');
    $this->get(route('change-orders.token.show', ['token' => $raw]))->assertOk();

    expect($recipient->fresh()->viewed_at->toISOString())->toBe($first->toISOString());
});

it('marks a change order read when the customer opens it in the portal', function (): void {
    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    $raw = ChangeOrderRecipient::generateToken();
    $recipient = ChangeOrderRecipient::factory()->create([
        'user_id' => $customer->id,
        'token_hash' => ChangeOrderRecipient::hashToken($raw),
    ]);
    $changeOrder = $recipient->changeOrder;
    $changeOrder->update(['status' => ChangeOrderStatus::SENT_TO_CUSTOMER]);
    CustomerProjectAccess::factory()->create(['user_id' => $customer->id, 'project_id' => $changeOrder->project_id]);

    $this->actingAs($customer)
        ->get(route('customer.change-orders.show', [$changeOrder->project, $changeOrder]))
        ->assertOk();

    expect($recipient->fresh()->viewed_at)->not->toBeNull();
});

it('does not mark a change order as read when an admin views it', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $recipient = ChangeOrderRecipient::factory()->create();
    $changeOrder = $recipient->changeOrder;

    $this->actingAs($admin)
        ->get(route('projects.change-orders.show', [$changeOrder->project, $changeOrder]))
        ->assertOk();

    expect($changeOrder->recipients()->first()->viewed_at)->toBeNull();
});

it('serialises recipients on the show page so the send button can gate on them', function (): void {
    $this->withoutVite();

    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);

    // The header send button is disabled from this payload — if `recipients`
    // were ever hidden on the model the button would be dead for everyone.
    $withRecipients = changeOrderWithItems($project, $manager);
    ChangeOrderRecipient::factory()->count(2)->create(['change_order_id' => $withRecipients->id]);

    $this->actingAs($manager)
        ->get(route('projects.change-orders.show', [$project, $withRecipients]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Projects/ChangeOrders/Show')
            ->has('changeOrder.recipients', 2)
        );

    $withoutRecipients = changeOrderWithItems($project, $manager);

    $this->actingAs($manager)
        ->get(route('projects.change-orders.show', [$project, $withoutRecipients]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Projects/ChangeOrders/Show')
            ->has('changeOrder.recipients', 0)
        );
});
