<?php

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Email\Actions\LogEmailAction;
use App\Domains\Email\Actions\SendCopEmailAction;
use App\Domains\Email\DTOs\SendEmailDTO;
use App\Domains\Email\Enums\EmailStatus;
use App\Domains\Email\Enums\EmailType;
use App\Domains\Email\Jobs\SendCopEmailJob;
use App\Domains\Email\Models\EmailLog;
use App\Domains\Email\Services\DynamicMailSender;
use App\Domains\Email\Services\EmailAttachmentPolicy;
use App\Domains\Email\Services\EmailComposerService;
use App\Domains\Markets\Models\Market;
use App\Domains\PdfGeneration\Actions\EmailProgressPdfAction;
use App\Domains\PdfGeneration\Enums\PdfType;
use App\Domains\PdfGeneration\Jobs\GeneratePartialPdfJob;
use App\Domains\PdfGeneration\Models\PackageRevision;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Enums\StageType;
use App\Domains\Templates\Models\ChecklistItem;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplatePictureCategory;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.brevo.key', 'test-brevo-key');
    config()->set('services.brevo.url', 'https://api.brevo.test/v3/smtp/email');
    // This suite exercises the real email delivery pipeline, so the
    // customer email kill switch must be off (enabled) here.
    config()->set('cop.customer_emails_enabled', true);
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Storage::fake('private');

    $this->fixture = function (): array {
        $market = Market::factory()->create(['name' => 'Ohio']);
        $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
        $manager->markets()->attach($market->id);
        $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id, 'name' => 'Lomas Peak']);
        $template = Template::factory()->create(['market_id' => $market->id]);

        foreach ($template->sections()->get() as $section) {
            TemplatePictureCategory::factory()->count(2)->create(['template_section_id' => $section->id]);
        }

        ChecklistItem::factory()->count(2)->create(['template_id' => $template->id]);

        $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
        $field->markets()->attach($market->id);
        $project->fieldSupervisors()->attach($field->id);
        $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

        return compact('market', 'manager', 'project', 'template', 'field', 'admin');
    };
});

it('creates an email log and sends a stage upload email to the manager', function (): void {
    Http::fake([
        'https://api.brevo.test/v3/smtp/email' => Http::response(['messageId' => 'm1'], 201),
    ]);

    $fixture = ($this->fixture)();
    $this->actingAs($fixture['field'])->post(route('packages.store', $fixture['project']), [
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);

    $package = CloseoutPackage::firstOrFail();
    $stage = $package->stages()->where('stage', StageType::PRE_CONSTRUCTION->value)->firstOrFail();

    $payload = $stage->categorySelections()->pluck('template_picture_category_id')->map(fn (int $id): array => [
        'category_id' => $id,
        'is_selected' => true,
        'picture_count' => 1,
    ])->all();

    $this->actingAs($fixture['field'])
        ->post(route('packages.stages.draft', [$package, StageType::PRE_CONSTRUCTION->value]), [
            'category_selections' => $payload,
            'checklist_responses' => [],
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($fixture['field'])
        ->post(route('packages.stages.submit', [$package, StageType::PRE_CONSTRUCTION->value]))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('email_logs', [
        'project_id' => $fixture['project']->id,
        'recipient_email' => $fixture['manager']->email,
        'type' => EmailType::STAGE_UPLOAD->value,
        'status' => EmailStatus::SENT->value,
    ]);

    Http::assertSent(function (Request $request) use ($fixture): bool {
        return $request->url() === 'https://api.brevo.test/v3/smtp/email'
            && $request->header('api-key')[0] === 'test-brevo-key'
            && $request['to'][0]['email'] === $fixture['manager']->email;
    });
});

it('deduplicates emails with the same idempotency key', function (): void {
    Http::fake([
        'https://api.brevo.test/v3/smtp/email' => Http::response(['messageId' => 'm1'], 201),
    ]);

    $fixture = ($this->fixture)();
    $package = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);
    $dto = new SendEmailDTO(
        to: 'manager@example.com',
        subject: 'Test',
        type: EmailType::STAGE_UPLOAD,
        body: 'Body',
        closeoutPackageId: $package->id,
        projectId: $fixture['project']->id,
        contextId: '99',
    );

    app(SendCopEmailAction::class)->execute($dto);
    app(SendCopEmailAction::class)->execute($dto);

    expect(EmailLog::count())->toBe(1);

    Http::assertSentCount(1);
});

it('emails the final PDF to the VZW engineer', function (): void {
    Http::fake([
        'https://api.brevo.test/v3/smtp/email' => Http::response(['messageId' => 'm1'], 201),
    ]);

    $fixture = ($this->fixture)();
    $package = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
        'status' => PackageStatus::APPROVED->value,
    ]);
    $revision = PackageRevision::factory()->create([
        'closeout_package_id' => $package->id,
        'storage_path' => 'pdfs/1/final_R1_1.pdf',
    ]);
    Storage::disk('private')->put('pdfs/1/final_R1_1.pdf', 'pdf');

    $this->actingAs($fixture['manager'])
        ->post(route('manager.packages.pdf.email', $package), [
            'email_to' => 'engineer@verizon.example',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    // Final PDF emails are now MANUAL: the intent is recorded as a READY
    // outbox row and nothing leaves until a manager releases it.
    $this->assertDatabaseHas('email_logs', [
        'closeout_package_id' => $package->id,
        'recipient_email' => 'engineer@verizon.example',
        'type' => EmailType::FINAL_PDF_EMAIL->value,
        'status' => EmailStatus::READY->value,
    ]);

    expect($revision->fresh()->emailed_to)->toBe('engineer@verizon.example')
        ->and($revision->fresh()->emailed_at)->not->toBeNull();

    Http::assertNothingSent();
});

it('does not crash when a concurrent request wins the idempotency race', function (): void {
    $fixture = ($this->fixture)();
    $package = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);
    $dto = new SendEmailDTO(
        to: 'manager@example.com',
        subject: 'Test',
        type: EmailType::FINAL_PDF_EMAIL,
        body: 'Body',
        closeoutPackageId: $package->id,
        projectId: $fixture['project']->id,
        contextId: '42',
    );
    $key = hash('sha256', 'final_pdf_email|'.$package->id.'|manager@example.com|42');

    // Simulate the loser of the race: a row with the same key already exists
    // (created by the concurrent winner between our check and our insert).
    EmailLog::create([
        'closeout_package_id' => $package->id,
        'project_id' => $fixture['project']->id,
        'recipient_email' => 'manager@example.com',
        'sender_email' => 'from@example.com',
        'subject' => 'Test',
        'type' => EmailType::FINAL_PDF_EMAIL->value,
        'status' => EmailStatus::PENDING->value,
        'idempotency_key' => $key,
    ]);

    $result = app(SendCopEmailAction::class)->execute($dto);

    expect(EmailLog::count())->toBe(1)
        ->and($result->id)->toBe(EmailLog::firstOrFail()->id);
});

it('reuses a failed email log and retries it on the next attempt', function (): void {
    Queue::fake();

    $fixture = ($this->fixture)();
    $package = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);
    // STAGE_UPLOAD is an internal (auto) type, so the retry path applies.
    $dto = new SendEmailDTO(
        to: 'manager@example.com',
        subject: 'Test',
        type: EmailType::STAGE_UPLOAD,
        body: 'Body',
        closeoutPackageId: $package->id,
        projectId: $fixture['project']->id,
        contextId: '43',
    );
    $key = hash('sha256', 'stage_upload|'.$package->id.'|manager@example.com|43');

    EmailLog::create([
        'closeout_package_id' => $package->id,
        'project_id' => $fixture['project']->id,
        'recipient_email' => 'manager@example.com',
        'sender_email' => 'from@example.com',
        'subject' => 'Test',
        'type' => EmailType::STAGE_UPLOAD->value,
        'status' => EmailStatus::FAILED->value,
        'idempotency_key' => $key,
    ]);

    $result = app(SendCopEmailAction::class)->execute($dto);

    expect(EmailLog::count())->toBe(1)
        ->and($result->fresh()->status)->toBe(EmailStatus::PENDING);

    Queue::assertPushed(SendCopEmailJob::class);
});

it('queues the progress PDF job with the recipient when emailing a progress PDF', function (): void {
    Queue::fake();

    $fixture = ($this->fixture)();
    $package = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);

    $this->actingAs($fixture['manager'])
        ->post(route('manager.packages.pdf.progress-email', $package), [
            'email_to' => 'client@example.com',
            'closeout_package_id' => $package->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Queue::assertPushed(
        GeneratePartialPdfJob::class,
        fn (GeneratePartialPdfJob $job): bool => $job->emailTo === 'client@example.com',
    );
});

it('records a generated progress PDF as a READY outbox intent on the revision', function (): void {
    $fixture = ($this->fixture)();
    $package = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);
    $revision = PackageRevision::factory()->create([
        'closeout_package_id' => $package->id,
        'type' => PdfType::PARTIAL->value,
        'storage_path' => 'temp/partial_R1_1.pdf',
    ]);
    Storage::disk('private')->put('temp/partial_R1_1.pdf', 'pdf-binary');

    app(EmailProgressPdfAction::class)->execute($package, $revision, 'client@example.com');

    // Progress PDF emails are now MANUAL: READY intent, nothing sent.
    $this->assertDatabaseHas('email_logs', [
        'closeout_package_id' => $package->id,
        'recipient_email' => 'client@example.com',
        'type' => EmailType::PROGRESS_PDF->value,
        'status' => EmailStatus::READY->value,
    ]);

    expect($revision->fresh()->emailed_to)->toBe('client@example.com')
        ->and($revision->fresh()->emailed_at)->not->toBeNull();

    Http::assertNothingSent();
});

it('lists email logs for admins and managers', function (): void {
    $this->withoutVite();

    $fixture = ($this->fixture)();
    EmailLog::factory()->create([
        'project_id' => $fixture['project']->id,
        'recipient_email' => 'manager@example.com',
    ]);

    $this->actingAs($fixture['admin'])
        ->get(route('admin.email-logs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/EmailLogs/Index')->has('logs', 1));

    $this->actingAs($fixture['manager'])
        ->get(route('manager.email-logs.manager'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Manager/EmailLogs/Index')->has('logs', 1));
});

it('records an oversized PDF attachment as a link-fallback intent for the outbox', function (): void {
    config(['app.key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=']);
    Queue::fake();
    Storage::fake('private');

    $fixture = ($this->fixture)();
    $package = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);
    $path = "pdfs/{$package->id}/final_R1.0.pdf";
    Storage::disk('private')->put($path, str_repeat('x', 16 * 1024 * 1024));

    $dto = new SendEmailDTO(
        to: 'vzw@verizon.example',
        type: EmailType::FINAL_PDF_EMAIL,
        closeoutPackageId: $package->id,
        attachmentPath: $path,
    );

    app(SendCopEmailAction::class)->execute($dto);

    // MANUAL type: no dispatch. The payload keeps the raw dto (the signed
    // link is regenerated at delivery); only the preview shows the fallback.
    Queue::assertNotPushed(SendCopEmailJob::class);

    $log = EmailLog::query()->where('recipient_email', 'vzw@verizon.example')->firstOrFail();

    expect($log->status)->toBe(EmailStatus::READY)
        ->and($log->payload['attachmentPath'])->toBe($path)
        ->and($log->body_preview)->toContain("/pdf/signed/{$package->id}");
});

it('records a within-limit PDF attachment intent without dispatching', function (): void {
    Queue::fake();
    Storage::fake('private');

    $fixture = ($this->fixture)();
    $package = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);
    $path = "pdfs/{$package->id}/final_R1.0.pdf";
    Storage::disk('private')->put($path, str_repeat('x', 1024));

    $dto = new SendEmailDTO(
        to: 'vzw@verizon.example',
        type: EmailType::FINAL_PDF_EMAIL,
        closeoutPackageId: $package->id,
        attachmentPath: $path,
    );

    app(SendCopEmailAction::class)->execute($dto);

    Queue::assertNotPushed(SendCopEmailJob::class);

    $log = EmailLog::query()->where('recipient_email', 'vzw@verizon.example')->firstOrFail();

    // Attachment path is preserved in the payload and resolved at delivery.
    expect($log->status)->toBe(EmailStatus::READY)
        ->and($log->payload['attachmentPath'])->toBe($path);
});

it('includes the Verizon engineer name on the final PDF email', function (): void {
    $fixture = ($this->fixture)();
    $package = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);
    $revision = PackageRevision::factory()->create(['closeout_package_id' => $package->id]);

    $dto = app(EmailComposerService::class)
        ->composeFinalPdfEmail($revision, 'vzw@verizon.example');

    expect($dto->toName)->toBe($fixture['project']->vzw_engineer_name);
});

it('falls back to a generic name when the project has no engineer name', function (): void {
    $fixture = ($this->fixture)();
    $fixture['project']->update(['vzw_engineer_name' => null]);
    $package = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);
    $revision = PackageRevision::factory()->create(['closeout_package_id' => $package->id]);

    $dto = app(EmailComposerService::class)
        ->composeFinalPdfEmail($revision, 'vzw@verizon.example');

    expect($dto->toName)->toBe('Verizon Engineer');
});

it('records the provider message id when Brevo accepts the email', function (): void {
    Http::fake([
        'https://api.brevo.test/v3/smtp/email' => Http::response(['messageId' => 'm42'], 201),
    ]);

    $fixture = ($this->fixture)();
    $package = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);
    $revision = PackageRevision::factory()->create(['closeout_package_id' => $package->id]);

    $dto = app(EmailComposerService::class)
        ->composeFinalPdfEmail($revision, 'vzw@verizon.example');

    $log = app(SendCopEmailAction::class)->execute($dto);
    (new SendCopEmailJob($log, $dto))->handle(app(DynamicMailSender::class), app(EmailAttachmentPolicy::class), app(LogEmailAction::class));

    expect($log->fresh()->status)->toBe(EmailStatus::SENT)
        ->and($log->fresh()->provider_message_id)->toBe('m42');
});
