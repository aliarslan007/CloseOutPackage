<?php

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\Email\Enums\EmailType;
use App\Domains\Email\Services\EmailComposerService;
use App\Domains\Email\Services\EmailSubjectBuilderService;
use App\Domains\Markets\Models\Market;
use App\Domains\PdfGeneration\Models\PackageRevision;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Models\Template;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('builds the canonical email subject format', function (): void {
    $market = Market::factory()->create(['name' => 'Ohio']);
    $project = Project::factory()->create(['market_id' => $market->id, 'name' => 'Lomas Peak']);

    $subject = app(EmailSubjectBuilderService::class)->build($project, 'Stage uploaded');

    expect($subject)->toBe('Lomas Peak → Ohio → Stage uploaded');
});

it('composes a stage upload email for the assigned manager', function (): void {
    $market = Market::factory()->create(['name' => 'Ohio']);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id, 'name' => 'Lomas Peak']);
    $template = Template::factory()->create(['market_id' => $market->id]);
    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
    ]);
    $stage = PackageStage::factory()->create([
        'closeout_package_id' => $package->id,
        'stage' => 'pre_construction',
        'submitted_at' => now(),
    ]);

    $dto = app(EmailComposerService::class)->composeStageUpload($stage);

    expect($dto)->not->toBeNull()
        ->and($dto->to)->toBe($manager->email)
        ->and($dto->type)->toBe(EmailType::STAGE_UPLOAD)
        ->and($dto->subject)->toBe('Lomas Peak → Ohio → Stage uploaded')
        ->and($dto->contextId)->toBe((string) $stage->id)
        ->and($dto->actionLabel)->toBe('Open Package')
        ->and($dto->actionUrl)->toBe(route('packages.show', $package->id))
        ->and($dto->body)->toMatch('/uploaded by .* on \w+ \d{1,2}, \d{4} at \d{1,2}:\d{2} (AM|PM)/');
});

it('composes a package-submitted email naming the submitter for the manager', function (): void {
    $market = Market::factory()->create(['name' => 'Ohio']);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $supervisor = User::factory()->create(['name' => 'Jordan Supervisor'])->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id, 'name' => 'Lomas Peak']);
    $template = Template::factory()->create(['market_id' => $market->id]);
    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
        'submitted_at' => now(),
    ]);

    $dto = app(EmailComposerService::class)->composePackageSubmitted($package, $supervisor);

    expect($dto)->not->toBeNull()
        ->and($dto->to)->toBe($manager->email)
        ->and($dto->type)->toBe(EmailType::GENERAL)
        ->and($dto->subject)->toBe('Lomas Peak → Ohio → Package submitted for review')
        ->and($dto->contextId)->toBe((string) $package->id)
        ->and($dto->actionLabel)->toBe('Review Package')
        ->and($dto->body)->toContain('submitted for review by Jordan Supervisor')
        ->and($dto->body)->toMatch('/ on \w+ \d{1,2}, \d{4} at \d{1,2}:\d{2} (AM|PM)/');
});

it('composes a replacement request email with the manager on CC', function (): void {
    $market = Market::factory()->create(['name' => 'Ohio']);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
    $template = Template::factory()->create(['market_id' => $market->id]);
    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
    ]);
    $photo = Photo::factory()->create([
        'closeout_package_id' => $package->id,
        'uploaded_by' => User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value)->id,
    ]);

    $dto = app(EmailComposerService::class)->composeReplacementRequest($photo);

    expect($dto)->not->toBeNull()
        ->and($dto->to)->toBe($photo->uploader->email)
        ->and($dto->cc)->toBe([$manager->email])
        ->and($dto->type)->toBe(EmailType::REPLACEMENT_REQUEST);
});

it('composes a final PDF email with the attachment path and manager on CC', function (): void {
    $market = Market::factory()->create(['name' => 'Ohio']);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
    $template = Template::factory()->create(['market_id' => $market->id]);
    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
    ]);
    $revision = PackageRevision::factory()->create([
        'closeout_package_id' => $package->id,
        'storage_path' => 'pdfs/1/final_R1_1.pdf',
    ]);

    $dto = app(EmailComposerService::class)->composeFinalPdfEmail($revision, 'engineer@verizon.example');

    expect($dto->to)->toBe('engineer@verizon.example')
        ->and($dto->cc)->toBe([$manager->email])
        ->and($dto->type)->toBe(EmailType::FINAL_PDF_EMAIL)
        ->and($dto->attachmentPath)->toBe('pdfs/1/final_R1_1.pdf');
});

it('composes a progress PDF email with the manager on CC', function (): void {
    $market = Market::factory()->create(['name' => 'Ohio']);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
    $template = Template::factory()->create(['market_id' => $market->id]);
    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
    ]);
    $revision = PackageRevision::factory()->create([
        'closeout_package_id' => $package->id,
        'storage_path' => 'temp/partial_R1_1.pdf',
        'revision_label' => 'R1.1',
    ]);

    $dto = app(EmailComposerService::class)->composeProgressPdf($package, $revision, 'client@example.com');

    expect($dto->to)->toBe('client@example.com')
        ->and($dto->cc)->toBe([$manager->email])
        ->and($dto->type)->toBe(EmailType::PROGRESS_PDF)
        ->and($dto->attachmentPath)->toBe('temp/partial_R1_1.pdf')
        ->and($dto->contextId)->toBe((string) $revision->id);
});

it('names the PDF attachment the recipient sees after the package format', function (): void {
    $market = Market::factory()->create(['name' => 'Ohio']);
    $project = Project::factory()->create([
        'market_id' => $market->id,
        'name' => 'Brrancca',
        'ps_number' => '17676316',
    ]);
    $template = Template::factory()->create(['market_id' => $market->id]);
    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
    ]);
    $revision = PackageRevision::factory()->create([
        'closeout_package_id' => $package->id,
        'storage_path' => 'pdfs/1/final_R2_0.pdf',
        'revision_major' => 2,
        'revision_minor' => 0,
        'revision_label' => 'R2.0',
        'revision_date' => '2026-09-20',
    ]);

    $dto = app(EmailComposerService::class)->composeFinalPdfEmail($revision, 'engineer@verizon.example');

    // The stored path stays internal; only the delivered name is client-facing.
    expect($dto->attachmentPath)->toBe('pdfs/1/final_R2_0.pdf')
        ->and($dto->attachmentFilename())->toBe('17676316-Brrancca-COP-09-20-2026-Rev2.0.pdf');
});
