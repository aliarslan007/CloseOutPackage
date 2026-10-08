<?php

use App\Domains\ChangeOrders\Actions\SendChangeOrderAction;
use App\Domains\ChangeOrders\Jobs\SendChangeOrderEmailJob;
use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\ChangeOrders\Models\ChangeOrderItem;
use App\Domains\ChangeOrders\Models\ChangeOrderRecipient;
use App\Domains\Email\Actions\DeliverCustomerEmailAction;
use App\Domains\Email\Actions\SendCopEmailAction;
use App\Domains\Email\DTOs\SendEmailDTO;
use App\Domains\Email\Enums\EmailStatus;
use App\Domains\Email\Enums\EmailType;
use App\Domains\Email\Jobs\SendCopEmailJob;
use App\Domains\Email\Models\EmailLog;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use App\Shared\Exceptions\EmailNotConfiguredException;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    config()->set('cop.customer_emails_enabled', false);
    Queue::fake();
});

function customerEmailDTO(EmailType $type = EmailType::PORTAL_ACCESS_GRANTED): SendEmailDTO
{
    return new SendEmailDTO(
        to: 'customer@example.com',
        toName: 'Verizon Customer',
        subject: 'Portal access granted',
        type: $type,
        body: 'You have been granted access to the project portal.',
        contextId: '42',
    );
}

it('suppresses customer-facing emails and records them instead of sending', function (): void {
    $log = app(SendCopEmailAction::class)->execute(customerEmailDTO());

    expect($log->status)->toBe(EmailStatus::SUPPRESSED)
        ->and($log->failed_reason)->toBe(SendCopEmailAction::SUPPRESSED_REASON)
        ->and($log->recipient_email)->toBe('customer@example.com');

    Queue::assertNotPushed(SendCopEmailJob::class);
});

it('does not duplicate suppressed logs when the same customer email triggers twice', function (): void {
    $action = app(SendCopEmailAction::class);

    $action->execute(customerEmailDTO());
    $action->execute(customerEmailDTO());

    expect(EmailLog::query()->count())->toBe(1)
        ->and(EmailLog::query()->sole()->status)->toBe(EmailStatus::SUPPRESSED);
});

it('still queues internal staff emails while customer emails are disabled', function (): void {
    $log = app(SendCopEmailAction::class)->execute(customerEmailDTO(EmailType::STAGE_UPLOAD));

    expect($log->status)->toBe(EmailStatus::PENDING);

    Queue::assertPushed(SendCopEmailJob::class);
});

it('sends customer emails once the kill switch is enabled', function (): void {
    config()->set('cop.customer_emails_enabled', true);

    $log = app(SendCopEmailAction::class)->execute(customerEmailDTO());

    expect($log->status)->toBe(EmailStatus::PENDING);

    Queue::assertPushed(SendCopEmailJob::class);
});

it('re-sends a previously suppressed email when it is triggered again after enabling', function (): void {
    $suppressed = app(SendCopEmailAction::class)->execute(customerEmailDTO());

    config()->set('cop.customer_emails_enabled', true);

    $retried = app(SendCopEmailAction::class)->execute(customerEmailDTO());

    expect($retried->is($suppressed))->toBeTrue()
        ->and($retried->refresh()->status)->toBe(EmailStatus::PENDING);

    Queue::assertPushed(SendCopEmailJob::class);
});

it('change order intents stay READY while the switch is off and release cleanly once enabled', function (): void {
    $creator = User::factory()->create()->assignRole(Role::MANAGER->value);
    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);

    $changeOrder = ChangeOrder::factory()->sent()->create(['created_by' => $creator->id]);
    ChangeOrderItem::factory()->create([
        'change_order_id' => $changeOrder->id,
        'is_included' => true,
    ]);
    ChangeOrderRecipient::factory()->create([
        'change_order_id' => $changeOrder->id,
        'user_id' => $customer->id,
    ]);

    // Recording the intent is unaffected by the switch.
    app(SendChangeOrderAction::class)->execute($changeOrder);

    $intent = EmailLog::query()
        ->where('change_order_id', $changeOrder->id)
        ->where('recipient_email', $customer->email)
        ->firstOrFail();

    expect($intent->status)->toBe(EmailStatus::READY);

    // Release is blocked while the switch is off...
    try {
        app(DeliverCustomerEmailAction::class)->sendNow($intent, $creator);

        $this->fail('Expected the release to be blocked by the kill switch.');
    } catch (EmailNotConfiguredException) {
        // expected
    }

    expect($intent->refresh()->status)->toBe(EmailStatus::READY);

    // ...and proceeds once the switch is back on. Release itself must not
    // flip the change order's sent state — that happens at delivery.
    $sentAtBeforeRelease = $changeOrder->fresh()->sent_at;

    config()->set('cop.customer_emails_enabled', true);

    $released = app(DeliverCustomerEmailAction::class)->sendNow($intent, $creator);

    expect($released->refresh()->status)->toBe(EmailStatus::PENDING)
        ->and($changeOrder->fresh()->sent_at)->toEqual($sentAtBeforeRelease);

    Queue::assertPushed(SendChangeOrderEmailJob::class);
});
