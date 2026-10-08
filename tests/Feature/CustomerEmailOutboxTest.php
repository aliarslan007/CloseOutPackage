<?php

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Email\Actions\DeliverCustomerEmailAction;
use App\Domains\Email\Actions\LogEmailAction;
use App\Domains\Email\DTOs\SendEmailDTO;
use App\Domains\Email\EmailScheduleInPastException;
use App\Domains\Email\EmailScheduleTooFarAheadException;
use App\Domains\Email\Enums\EmailStatus;
use App\Domains\Email\Enums\EmailType;
use App\Domains\Email\InvalidOutboxTransitionException;
use App\Domains\Email\Jobs\SendCopEmailJob;
use App\Domains\Email\Models\EmailLog;
use App\Domains\Email\Services\DynamicMailSender;
use App\Domains\Email\Services\EmailAttachmentPolicy;
use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use App\Shared\Exceptions\EmailNotConfiguredException;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.brevo.key', 'test-brevo-key');
    config()->set('services.brevo.url', 'https://api.brevo.test/v3/smtp/email');
    config()->set('cop.customer_emails_enabled', true);
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Storage::fake('private');

    $this->manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $this->otherManager = User::factory()->create()->assignRole(Role::MANAGER->value);
});

function outboxDTO(Project $project, EmailType $type = EmailType::FINAL_PDF_EMAIL, string $context = '500'): SendEmailDTO
{
    return new SendEmailDTO(
        to: 'engineer@verizon.example',
        toName: 'VZW Engineer',
        subject: 'Final PDF Package — Lomas Peak',
        type: $type,
        body: 'Your final package is attached.',
        closeoutPackageId: null,
        projectId: $project->id,
        contextId: $context,
    );
}

function readyIntent(Project $project, array $overrides = []): EmailLog
{
    return EmailLog::factory()->ready()->create(array_merge([
        'project_id' => $project->id,
        'recipient_email' => 'engineer@verizon.example',
        'type' => EmailType::FINAL_PDF_EMAIL,
        'subject' => 'Final PDF Package — Lomas Peak',
        'context_id' => '500',
        'payload' => [
            'to' => 'engineer@verizon.example',
            'toName' => 'VZW Engineer',
            'cc' => [],
            'subject' => 'Final PDF Package — Lomas Peak',
            'type' => EmailType::FINAL_PDF_EMAIL->value,
            'body' => 'Your final package is attached.',
            'projectId' => $project->id,
            'contextId' => '500',
        ],
    ], $overrides));
}

it('sends a READY intent now and records sent_at, provider id and the releasing manager', function (): void {
    Http::fake([
        'https://api.brevo.test/v3/smtp/email' => Http::response(['messageId' => 'mx1'], 201),
    ]);
    Queue::fake();

    $intent = readyIntent(project());

    $released = app(DeliverCustomerEmailAction::class)->sendNow($intent, $this->manager);

    expect($released->refresh()->status)->toBe(EmailStatus::PENDING)
        ->and($released->created_by)->toBe($this->manager->id);

    Queue::assertPushed(SendCopEmailJob::class);

    // Worker pass: the queued job delivers and finalizes the row.
    (new SendCopEmailJob($released))->handle(
        app(DynamicMailSender::class),
        app(EmailAttachmentPolicy::class),
        app(LogEmailAction::class),
    );

    $intent->refresh();

    expect($intent->status)->toBe(EmailStatus::SENT)
        ->and($intent->sent_at)->not->toBeNull()
        ->and($intent->provider_message_id)->toBe('mx1');
});

it('schedules a READY intent with a future time and dispatches a delayed job', function (): void {
    Queue::fake();

    $intent = readyIntent(project());
    $sendAt = now()->addMinutes(30);

    $scheduled = app(DeliverCustomerEmailAction::class)->schedule($intent, $sendAt, $this->manager);

    expect($scheduled->refresh()->status)->toBe(EmailStatus::SCHEDULED)
        ->and($scheduled->scheduled_at->format('Y-m-d H:i:s'))->toBe($sendAt->format('Y-m-d H:i:s'))
        ->and($scheduled->created_by)->toBe($this->manager->id);

    Queue::assertPushed(SendCopEmailJob::class);
});

it('rejects scheduling in the past', function (): void {
    Queue::fake();

    $intent = readyIntent(project());

    app(DeliverCustomerEmailAction::class)->schedule($intent, now()->subMinutes(5), $this->manager);

    expect($intent->refresh()->status)->toBe(EmailStatus::READY);
})->throws(EmailScheduleInPastException::class);

it('rejects scheduling beyond the 30-day horizon', function (): void {
    Queue::fake();

    $intent = readyIntent(project());

    app(DeliverCustomerEmailAction::class)->schedule($intent, now()->addDays(31), $this->manager);

    expect($intent->refresh()->status)->toBe(EmailStatus::READY);
})->throws(EmailScheduleTooFarAheadException::class);

it('cancels a scheduled send and the delayed job no-ops when it fires', function (): void {
    Http::fake();
    Queue::fake();

    $intent = readyIntent(project());
    $action = app(DeliverCustomerEmailAction::class);

    $scheduled = $action->schedule($intent, now()->addHour(), $this->manager);
    $cancelled = $action->cancel($scheduled, $this->manager);

    expect($cancelled->status)->toBe(EmailStatus::CANCELLED)
        ->and($cancelled->cancelled_at)->not->toBeNull()
        ->and($cancelled->created_by)->toBe($this->manager->id);

    // The delayed job fires after the cancel: it must not send.
    (new SendCopEmailJob($cancelled))->handle(
        app(DynamicMailSender::class),
        app(EmailAttachmentPolicy::class),
        app(LogEmailAction::class),
    );

    expect($cancelled->refresh()->status)->toBe(EmailStatus::CANCELLED);

    Http::assertNothingSent();
});

it('resends a sent email as a NEW row with an attempt-suffixed key, leaving the original intact', function (): void {
    Http::fake([
        'https://api.brevo.test/v3/smtp/email' => Http::response(['messageId' => 'mx2'], 201),
    ]);
    Queue::fake();

    $sent = readyIntent(project(), [
        'status' => EmailStatus::SENT,
        'sent_at' => now()->subDay(),
        'provider_message_id' => 'original-msg',
        'idempotency_key' => hash('sha256', 'final_pdf_email|null|engineer@verizon.example|500'),
    ]);

    $resend = app(DeliverCustomerEmailAction::class)->resend($sent, $this->manager);

    expect(EmailLog::count())->toBe(2)
        ->and($resend->is($sent))->toBeFalse()
        ->and($resend->idempotency_key)->not->toBe($sent->idempotency_key)
        ->and($resend->idempotency_key)->toEndWith('|a:2')
        ->and(strlen((string) $resend->idempotency_key))->toBeLessThanOrEqual(64)
        ->and($resend->created_by)->toBe($this->manager->id);

    // Original row untouched.
    $sent->refresh();
    expect($sent->status)->toBe(EmailStatus::SENT)
        ->and($sent->provider_message_id)->toBe('original-msg');

    // The fresh copy is in the queue for delivery.
    Queue::assertPushed(SendCopEmailJob::class);

    (new SendCopEmailJob($resend))->handle(
        app(DynamicMailSender::class),
        app(EmailAttachmentPolicy::class),
        app(LogEmailAction::class),
    );

    expect($resend->refresh()->status)->toBe(EmailStatus::SENT)
        ->and($resend->provider_message_id)->toBe('mx2');
});

it('blocks resending while the kill switch is off', function (): void {
    config()->set('cop.customer_emails_enabled', false);
    Queue::fake();

    $sent = readyIntent(project(), ['status' => EmailStatus::SENT, 'sent_at' => now()]);

    app(DeliverCustomerEmailAction::class)->resend($sent, $this->manager);

    expect(EmailLog::count())->toBe(1);
})->throws(EmailNotConfiguredException::class);

it('retries a FAILED delivery in place', function (): void {
    Http::fake([
        'https://api.brevo.test/v3/smtp/email' => Http::response(['messageId' => 'mx3'], 201),
    ]);
    Queue::fake();

    $failed = readyIntent(project(), [
        'status' => EmailStatus::FAILED,
        'failed_reason' => 'Brevo 502',
    ]);

    $retried = app(DeliverCustomerEmailAction::class)->retry($failed, $this->manager);

    expect($retried->refresh()->status)->toBe(EmailStatus::PENDING)
        ->and($retried->failed_reason)->toBeNull()
        ->and($retried->created_by)->toBe($this->manager->id);

    Queue::assertPushed(SendCopEmailJob::class);
});

it('a superseded delayed job no-ops after the row is rescheduled', function (): void {
    Http::fake();
    Queue::fake();

    $intent = readyIntent(project());
    $action = app(DeliverCustomerEmailAction::class);

    // First schedule: a delayed job goes out carrying the first time.
    $action->schedule($intent, $firstAt = now()->addHour(), $this->manager);

    // Reschedule: the row moves to a later time.
    $action->schedule($intent->refresh(), $secondAt = now()->addHours(2), $this->manager);

    expect($intent->refresh()->scheduled_at->format('H:i:s'))->toBe($secondAt->format('H:i:s'));

    // The OLD job eventually fires with its stale snapshot — it must not send.
    (new SendCopEmailJob($intent, null, $firstAt->toIso8601String()))->handle(
        app(DynamicMailSender::class),
        app(EmailAttachmentPolicy::class),
        app(LogEmailAction::class),
    );

    expect($intent->refresh()->status)->toBe(EmailStatus::SCHEDULED);

    Http::assertNothingSent();

    // The NEW job's snapshot matches and delivers.
    (new SendCopEmailJob($intent, null, $secondAt->toIso8601String()))->handle(
        app(DynamicMailSender::class),
        app(EmailAttachmentPolicy::class),
        app(LogEmailAction::class),
    );

    expect($intent->refresh()->status)->toBe(EmailStatus::SENT);
});

it('refuses to send or schedule a row that is not open', function (): void {
    Queue::fake();

    $sent = readyIntent(project(), ['status' => EmailStatus::SENT, 'sent_at' => now()]);
    $cancelled = readyIntent(project(['context_id' => 'x']), ['status' => EmailStatus::CANCELLED, 'context_id' => 'x']);

    $action = app(DeliverCustomerEmailAction::class);

    $action->sendNow($sent, $this->manager);
})->throws(InvalidOutboxTransitionException::class);

it('hydrates the dto from the stored payload when the job runs outbox-style', function (): void {
    Http::fake([
        'https://api.brevo.test/v3/smtp/email' => Http::response(['messageId' => 'mx4'], 201),
    ]);

    $intent = readyIntent(project());

    // Outbox-style construction: DTO omitted, hydrated from payload.
    (new SendCopEmailJob($intent))->handle(
        app(DynamicMailSender::class),
        app(EmailAttachmentPolicy::class),
        app(LogEmailAction::class),
    );

    $intent->refresh();

    expect($intent->status)->toBe(EmailStatus::SENT)
        ->and($intent->provider_message_id)->toBe('mx4');

    Http::assertSent(fn (Request $request): bool => $request['to'][0]['email'] === 'engineer@verizon.example');
});

it('re-runs the attachment policy at delivery so scheduled PDFs get fresh signed links', function (): void {
    config(['app.key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=']);
    Http::fake([
        'https://api.brevo.test/v3/smtp/email' => Http::response(['messageId' => 'mx5'], 201),
    ]);

    $package = CloseoutPackage::factory()->create(['project_id' => project()->id]);
    $path = "pdfs/{$package->id}/final_R1.0.pdf";
    Storage::disk('private')->put($path, str_repeat('x', 16 * 1024 * 1024));

    $intent = readyIntent(project(), [
        'closeout_package_id' => $package->id,
        'payload' => [
            'to' => 'engineer@verizon.example',
            'type' => EmailType::FINAL_PDF_EMAIL->value,
            'body' => 'Your final package.',
            'closeoutPackageId' => $package->id,
            'projectId' => project()->id,
            'attachmentPath' => $path,
            'contextId' => '500',
        ],
    ]);

    (new SendCopEmailJob($intent))->handle(
        app(DynamicMailSender::class),
        app(EmailAttachmentPolicy::class),
        app(LogEmailAction::class),
    );

    Http::assertSent(function (Request $request) use ($package): bool {
        return str_contains($request['htmlContent'] ?? $request['textContent'] ?? '', "/pdf/signed/{$package->id}");
    });
});

it('sweeps and dispatches due scheduled emails via console command', function (): void {
    Queue::fake();

    $project = project();
    $pastIntent = readyIntent($project, [
        'status' => EmailStatus::SCHEDULED->value,
        'scheduled_at' => now()->subMinute(),
        'payload' => outboxDTO($project)->toArray(),
    ]);

    $futureIntent = readyIntent($project, [
        'status' => EmailStatus::SCHEDULED->value,
        'scheduled_at' => now()->addHour(),
        'payload' => outboxDTO($project)->toArray(),
    ]);

    $this->artisan('cop:process-scheduled-emails')
        ->assertSuccessful();

    $pastIntent->refresh();
    $futureIntent->refresh();

    expect($pastIntent->status)->toBe(EmailStatus::PENDING)
        ->and($pastIntent->scheduled_at)->toBeNull()
        ->and($futureIntent->status)->toBe(EmailStatus::SCHEDULED);

    Queue::assertPushed(SendCopEmailJob::class, fn (SendCopEmailJob $job): bool => $job->log->id === $pastIntent->id);
});

// ---------------------------------------------------------------- helpers

function project(): Project
{
    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);

    return Project::factory()->create([
        'market_id' => $market->id,
        'manager_id' => $manager->id,
    ]);
}
