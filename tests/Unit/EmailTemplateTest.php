<?php

use App\Domains\Email\Enums\EmailType;
use App\Domains\EmailTemplates\Actions\CreateEmailTemplateAction;
use App\Domains\EmailTemplates\Actions\DeleteEmailTemplateAction;
use App\Domains\EmailTemplates\Actions\DuplicateEmailTemplateAction;
use App\Domains\EmailTemplates\Actions\SetDefaultEmailTemplateAction;
use App\Domains\EmailTemplates\Actions\UpdateEmailTemplateAction;
use App\Domains\EmailTemplates\DTOs\CreateEmailTemplateDTO;
use App\Domains\EmailTemplates\DTOs\UpdateEmailTemplateDTO;
use App\Domains\EmailTemplates\Enums\EmailTemplateStatus;
use App\Domains\EmailTemplates\Models\EmailTemplate;
use App\Domains\EmailTemplates\Queries\EmailTemplateQuery;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\DefaultEmailTemplateSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $this->manager = User::factory()->create()->assignRole(Role::MANAGER->value);
});

it('casts email_type, status and is_default', function (): void {
    $template = EmailTemplate::factory()->create([
        'email_type' => EmailType::PROGRESS_PDF,
        'status' => EmailTemplateStatus::DRAFT,
        'is_default' => true,
    ])->fresh();

    expect($template->email_type)->toBe(EmailType::PROGRESS_PDF)
        ->and($template->status)->toBe(EmailTemplateStatus::DRAFT)
        ->and($template->is_default)->toBeTrue();
});

it('scopes active templates by type', function (): void {
    EmailTemplate::factory()->create(['email_type' => EmailType::FINAL_PDF_EMAIL]);
    EmailTemplate::factory()->create([
        'email_type' => EmailType::FINAL_PDF_EMAIL,
        'status' => EmailTemplateStatus::ARCHIVED,
    ]);
    EmailTemplate::factory()->create(['email_type' => EmailType::PROGRESS_PDF]);

    expect(EmailTemplate::active()->forType(EmailType::FINAL_PDF_EMAIL)->count())->toBe(1)
        ->and(EmailTemplate::active()->count())->toBe(2)
        ->and(EmailTemplate::forType(EmailType::FINAL_PDF_EMAIL)->count())->toBe(2);
});

it('promotes a new default and demotes the previous one for the same type', function (): void {
    $action = app(CreateEmailTemplateAction::class);
    $setDefault = app(SetDefaultEmailTemplateAction::class);

    $first = $action->execute(new CreateEmailTemplateDTO(
        emailType: EmailType::FINAL_PDF_EMAIL,
        name: 'First',
        subjectTemplate: 's',
        bodyTemplate: 'b',
        isDefault: true,
    ), $this->admin);

    $second = $action->execute(new CreateEmailTemplateDTO(
        emailType: EmailType::FINAL_PDF_EMAIL,
        name: 'Second',
        subjectTemplate: 's',
        bodyTemplate: 'b',
    ), $this->admin);

    $setDefault->execute($second);

    expect($first->refresh()->is_default)->toBeFalse()
        ->and($second->refresh()->is_default)->toBeTrue()
        ->and(EmailTemplate::query()
            ->where('email_type', EmailType::FINAL_PDF_EMAIL->value)
            ->where('is_default', true)
            ->count())->toBe(1);
});

it('keeps one default per type across different types', function (): void {
    $action = app(CreateEmailTemplateAction::class);

    $final = $action->execute(new CreateEmailTemplateDTO(
        emailType: EmailType::FINAL_PDF_EMAIL,
        name: 'Final',
        subjectTemplate: 's',
        bodyTemplate: 'b',
        isDefault: true,
    ), $this->admin);

    $progress = $action->execute(new CreateEmailTemplateDTO(
        emailType: EmailType::PROGRESS_PDF,
        name: 'Progress',
        subjectTemplate: 's',
        bodyTemplate: 'b',
        isDefault: true,
    ), $this->admin);

    expect($final->refresh()->is_default)->toBeTrue()
        ->and($progress->refresh()->is_default)->toBeTrue();
});
it('demotes a template when the update no longer flags it as default', function (): void {
    $template = EmailTemplate::factory()->create([
        'email_type' => EmailType::FINAL_PDF_EMAIL,
        'status' => EmailTemplateStatus::ACTIVE,
        'is_default' => true,
    ]);

    app(UpdateEmailTemplateAction::class)->execute($template, new UpdateEmailTemplateDTO(
        emailType: EmailType::FINAL_PDF_EMAIL,
        name: $template->name,
        subjectTemplate: $template->subject_template,
        bodyTemplate: $template->body_template,
        status: EmailTemplateStatus::DRAFT,
        isDefault: false,
    ));

    expect($template->refresh()->is_default)->toBeFalse()
        ->and($template->status)->toBe(EmailTemplateStatus::DRAFT);
});

it('duplicates a template as a non-default draft', function (): void {
    $template = EmailTemplate::factory()->create([
        'email_type' => EmailType::FINAL_PDF_EMAIL,
        'name' => 'Standard',
        'is_default' => true,
    ]);

    $copy = app(DuplicateEmailTemplateAction::class)->execute($template, $this->admin);

    expect($copy->name)->toBe('Standard Copy')
        ->and($copy->status)->toBe(EmailTemplateStatus::DRAFT)
        ->and($copy->is_default)->toBeFalse()
        ->and($copy->created_by)->toBe($this->admin->id)
        ->and($copy->email_type)->toBe(EmailType::FINAL_PDF_EMAIL);
});

it('soft deletes a template', function (): void {
    $template = EmailTemplate::factory()->create();

    app(DeleteEmailTemplateAction::class)->execute($template);

    expect(EmailTemplate::query()->whereKey($template->id)->exists())->toBeFalse()
        ->and(EmailTemplate::withTrashed()->whereKey($template->id)->exists())->toBeTrue();
});

it('picks only active templates and finds the type default', function (): void {
    $query = app(EmailTemplateQuery::class);

    EmailTemplate::factory()->create([
        'email_type' => EmailType::FINAL_PDF_EMAIL,
        'name' => 'Alpha',
        'is_default' => true,
    ]);
    EmailTemplate::factory()->create([
        'email_type' => EmailType::FINAL_PDF_EMAIL,
        'name' => 'Beta',
    ]);
    EmailTemplate::factory()->create([
        'email_type' => EmailType::FINAL_PDF_EMAIL,
        'name' => 'Gamma',
        'status' => EmailTemplateStatus::ARCHIVED,
    ]);

    $picker = $query->forTypePicker(EmailType::FINAL_PDF_EMAIL);

    expect($picker->pluck('name')->all())->toBe(['Alpha', 'Beta'])
        ->and($query->defaultForType(EmailType::FINAL_PDF_EMAIL)?->name)->toBe('Alpha')
        ->and($query->paginated('Bet')->total())->toBe(1);
});

it('seeds one default template per email type idempotently', function (): void {
    $this->seed(DefaultEmailTemplateSeeder::class);
    $this->seed(DefaultEmailTemplateSeeder::class);

    expect(EmailTemplate::count())->toBe(count(EmailType::cases()))
        ->and(EmailTemplate::where('is_default', true)->count())->toBe(count(EmailType::cases()));

    foreach (EmailType::cases() as $type) {
        expect(EmailTemplate::active()->forType($type)->where('is_default', true)->count())->toBe(1);
    }
});

it('gates the library by role', function (): void {
    $template = EmailTemplate::factory()->create();
    $tech = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    expect($this->admin->can('create', EmailTemplate::class))->toBeTrue()
        ->and($this->admin->can('update', $template))->toBeTrue()
        ->and($this->admin->can('setDefault', $template))->toBeTrue()
        ->and($this->manager->can('view', $template))->toBeTrue()
        ->and($this->manager->can('create', EmailTemplate::class))->toBeTrue()
        ->and($this->manager->can('update', $template))->toBeTrue()
        ->and($tech->can('create', EmailTemplate::class))->toBeFalse()
        ->and($tech->can('update', $template))->toBeFalse();
});
