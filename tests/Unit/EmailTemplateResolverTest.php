<?php

use App\Domains\Email\DTOs\SendEmailDTO;
use App\Domains\Email\Enums\EmailType;
use App\Domains\EmailTemplates\Models\EmailTemplate;
use App\Domains\EmailTemplates\Services\EmailTemplateDefaultDefinitions;
use App\Domains\EmailTemplates\Services\EmailTemplateResolver;
use App\Domains\EmailTemplates\Services\EmailTemplateVariableRegistry;
use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->resolver = fn (): EmailTemplateResolver => app(EmailTemplateResolver::class);

    $this->fixture = function (): array {
        $market = Market::factory()->create(['name' => 'Ohio Market']);
        $manager = User::factory()->create(['name' => 'Dana Manager']);
        $project = Project::factory()->create([
            'market_id' => $market->id,
            'manager_id' => $manager->id,
            'name' => 'Columbus North Macro 5G',
            'ps_number' => 'PS-OH-44021',
            'vzw_engineer_name' => 'VZW Engineer',
        ]);

        return compact('market', 'manager', 'project');
    };
});

it('resolves project and recipient tokens from real data', function (): void {
    $fixture = ($this->fixture)();
    $template = EmailTemplate::factory()->create([
        'email_type' => EmailType::FINAL_PDF_EMAIL,
        'subject_template' => '{{site_name}} → {{market_name}}',
        'body_template' => 'PS {{ps_number}} for {{recipient_name}} ({{manager_name}}, {{vzw_engineer_name}})',
    ]);

    $dto = new SendEmailDTO(
        to: 'vzw@example.com',
        toName: 'Bob Recipient',
        subject: 'base subject',
        type: EmailType::FINAL_PDF_EMAIL,
        body: 'base body',
        projectId: $fixture['project']->id,
    );

    $resolved = ($this->resolver)()->resolve($dto, $template, null, null, ['revision_label' => 'R2.4']);

    expect($resolved->subject)->toBe('Columbus North Macro 5G → Ohio Market')
        ->and($resolved->body)->toBe('PS PS-OH-44021 for Bob Recipient (Dana Manager, VZW Engineer)');
});

it('leaves unresolvable tokens verbatim instead of blanking them', function (): void {
    $template = EmailTemplate::factory()->create([
        'email_type' => EmailType::FINAL_PDF_EMAIL,
        'subject_template' => '{{site_name}} {{revision_label}} {{unknown_token}}',
        'body_template' => 'body',
    ]);

    $dto = new SendEmailDTO(to: 'a@b.com', type: EmailType::FINAL_PDF_EMAIL);

    $resolved = ($this->resolver)()->resolve($dto, $template, null, null, ['revision_label' => 'R2.4']);

    expect($resolved->subject)->toBe('{{site_name}} R2.4 {{unknown_token}}');
});

it('does not mutate the incoming DTO', function (): void {
    $fixture = ($this->fixture)();
    $template = EmailTemplate::factory()->create([
        'email_type' => EmailType::FINAL_PDF_EMAIL,
        'subject_template' => 'Templated {{site_name}}',
        'body_template' => 'Templated body',
    ]);

    $dto = new SendEmailDTO(
        to: 'a@b.com',
        subject: 'original subject',
        type: EmailType::FINAL_PDF_EMAIL,
        body: 'original body',
        projectId: $fixture['project']->id,
    );

    $resolved = ($this->resolver)()->resolve($dto, $template);

    expect($dto->subject)->toBe('original subject')
        ->and($dto->body)->toBe('original body')
        ->and($resolved->subject)->not->toBe($dto->subject);
});

it('lets the subject and body overrides win over the template', function (): void {
    $fixture = ($this->fixture)();
    $template = EmailTemplate::factory()->create([
        'email_type' => EmailType::FINAL_PDF_EMAIL,
        'subject_template' => 'Template subject',
        'body_template' => 'Template body',
    ]);

    $dto = new SendEmailDTO(
        to: 'a@b.com',
        type: EmailType::FINAL_PDF_EMAIL,
        projectId: $fixture['project']->id,
    );

    $resolved = ($this->resolver)()->resolve(
        $dto,
        $template,
        'Edited {{site_name}}',
        'Edited body',
    );

    expect($resolved->subject)->toBe('Edited Columbus North Macro 5G')
        ->and($resolved->body)->toBe('Edited body');
});

it('treats an empty-string override as "use the template value"', function (): void {
    $template = EmailTemplate::factory()->create([
        'email_type' => EmailType::PROGRESS_PDF,
        'subject_template' => 'Template subject',
        'body_template' => 'Template body',
    ]);

    $dto = new SendEmailDTO(to: 'a@b.com', type: EmailType::PROGRESS_PDF);

    $resolved = ($this->resolver)()->resolve($dto, $template, '', '');

    expect($resolved->subject)->toBe('Template subject')
        ->and($resolved->body)->toBe('Template body');
});

it('previews raw subject/body against a real project without side effects', function (): void {
    $fixture = ($this->fixture)();

    $preview = ($this->resolver)()->preview(
        'Hello {{recipient_name}} of {{site_name}}',
        'PS {{ps_number}} | {{revision_label}}',
        $fixture['project']->id,
    );

    expect($preview['subject'])->toBe('Hello {{recipient_name}} of Columbus North Macro 5G')
        ->and($preview['body'])->toBe('PS PS-OH-44021 | {{revision_label}}');
});

it('exposes the supported tokens per email type', function (): void {
    $registry = app(EmailTemplateVariableRegistry::class);

    expect($registry->variablesFor(EmailType::FINAL_PDF_EMAIL))
        ->toContain('revision_label', 'vzw_engineer_name', 'site_name')
        ->and($registry->variablesFor(EmailType::ISSUE_FORWARD))->toContain('issue_type')
        ->and($registry->variablesFor(EmailType::ISSUE_FORWARD))->not->toContain('revision_label')
        ->and($registry->all())->toHaveCount(count(EmailType::cases()));
});

it('resolveDefault uses active default template when present', function (): void {
    $fixture = ($this->fixture)();
    EmailTemplate::factory()->create([
        'email_type' => EmailType::CHANGE_ORDER,
        'is_default' => true,
        'subject_template' => 'CO {{change_order_number}} for {{site_name}}',
        'body_template' => 'Amount: {{total_amount}}',
    ]);

    $dto = new SendEmailDTO(
        to: 'customer@example.com',
        type: EmailType::CHANGE_ORDER,
        projectId: $fixture['project']->id,
    );

    $resolved = ($this->resolver)()->resolveDefault($dto, EmailType::CHANGE_ORDER, [
        'change_order_number' => 'CO-101',
        'total_amount' => '$5,400.00',
    ]);

    expect($resolved->subject)->toBe('CO CO-101 for Columbus North Macro 5G')
        ->and($resolved->body)->toBe('Amount: $5,400.00');
});

it('resolveDefault safely falls back to original DTO if no template exists', function (): void {
    $dto = new SendEmailDTO(
        to: 'customer@example.com',
        subject: 'Fallback Subject',
        type: EmailType::PORTAL_ACCESS_REVOKED,
        body: 'Fallback Body',
    );

    $resolved = ($this->resolver)()->resolveDefault($dto, EmailType::PORTAL_ACCESS_REVOKED);

    expect($resolved->subject)->toBe('Fallback Subject')
        ->and($resolved->body)->toBe('Fallback Body');
});

it('EmailTemplateDefaultDefinitions covers all EmailType cases', function (): void {
    $defaults = EmailTemplateDefaultDefinitions::all();

    expect($defaults)->toHaveCount(count(EmailType::cases()))
        ->and($defaults)->toHaveKey(EmailType::PORTAL_ACCESS_GRANTED->value)
        ->and($defaults)->toHaveKey(EmailType::CUSTOMER_REVIEW_OPENED->value)
        ->and($defaults)->toHaveKey(EmailType::CHANGE_ORDER->value);
});
