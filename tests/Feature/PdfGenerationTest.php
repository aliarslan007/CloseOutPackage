<?php

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Markets\Models\Market;
use App\Domains\PdfGeneration\Enums\PdfRevisionStatus;
use App\Domains\PdfGeneration\Enums\PdfType;
use App\Domains\PdfGeneration\Jobs\GeneratePartialPdfJob;
use App\Domains\PdfGeneration\Jobs\GeneratePdfJob;
use App\Domains\PdfGeneration\Models\PackageRevision;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Models\Template;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Storage::fake('private');

    $this->fixture = function (): array {
        $market = Market::factory()->create();
        $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
        $manager->markets()->attach($market->id);
        $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
        $template = Template::factory()->create(['market_id' => $market->id]);
        $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
        $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
        $project->fieldSupervisors()->attach($field->id);

        return compact('market', 'manager', 'project', 'template', 'admin', 'field');
    };

    $this->package = function (array $fixture, string $status = PackageStatus::APPROVED->value): CloseoutPackage {
        return CloseoutPackage::factory()->create([
            'project_id' => $fixture['project']->id,
            'template_id' => $fixture['template']->id,
            'status' => $status,
        ]);
    };
});

it('rejects final PDF generation for a non-approved package', function (): void {
    $fixture = ($this->fixture)();
    $package = ($this->package)($fixture, PackageStatus::DRAFT->value);

    $this->actingAs($fixture['manager'])
        ->post(route('manager.packages.pdf.final', $package), ['closeout_package_id' => $package->id])
        ->assertSessionHasErrors('closeout_package_id');
});

it('dispatches the final PDF job preserving the package revision', function (): void {
    Queue::fake();

    $fixture = ($this->fixture)();
    $package = ($this->package)($fixture);

    // Initial final PDF generation preserves package revision (R1.0)
    $this->actingAs($fixture['manager'])
        ->post(route('manager.packages.pdf.final', $package), ['closeout_package_id' => $package->id])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    Queue::assertPushed(GeneratePdfJob::class, fn (GeneratePdfJob $job): bool => $job->queue === 'pdf');

    expect($package->fresh())
        ->revision_major->toBe(1)
        ->revision_minor->toBe(0);

    // Simulate final PDF generated
    $package->update(['final_pdf_path' => 'packages/final.pdf']);

    // Regenerating preserves R1.0
    $this->actingAs($fixture['manager'])
        ->post(route('manager.packages.pdf.final', $package), ['closeout_package_id' => $package->id])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($package->fresh())
        ->revision_major->toBe(1)
        ->revision_minor->toBe(0);
});

it('dispatches the partial PDF job with an optional recipient', function (): void {
    Queue::fake();

    $fixture = ($this->fixture)();
    $package = ($this->package)($fixture);
    Photo::factory()->create(['closeout_package_id' => $package->id]);

    $this->actingAs($fixture['manager'])
        ->post(route('manager.packages.pdf.partial', $package), [
            'closeout_package_id' => $package->id,
            'email_to' => 'engineer@verizon.example',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Queue::assertPushed(GeneratePartialPdfJob::class, fn (GeneratePartialPdfJob $job): bool => $job->queue === 'pdf');

    expect($package->fresh()->revision_minor)->toBe(0);
});

it('rejects the partial PDF request when the email is invalid', function (): void {
    Queue::fake();

    $fixture = ($this->fixture)();
    $package = ($this->package)($fixture);

    $this->actingAs($fixture['manager'])
        ->post(route('manager.packages.pdf.partial', $package), [
            'closeout_package_id' => $package->id,
            'email_to' => 'not-an-email',
        ])
        ->assertSessionHasErrors('email_to');

    Queue::assertNothingPushed();
});

it('rejects the partial PDF request when the package has no photos', function (): void {
    $fixture = ($this->fixture)();
    $package = ($this->package)($fixture);

    $this->actingAs($fixture['manager'])
        ->post(route('manager.packages.pdf.partial', $package), [
            'closeout_package_id' => $package->id,
        ])
        ->assertSessionHasErrors('closeout_package_id');
});

it('denies PDF generation to field supervisors', function (): void {
    $fixture = ($this->fixture)();
    $package = ($this->package)($fixture);

    $this->actingAs($fixture['field'])
        ->post(route('manager.packages.pdf.final', $package), ['closeout_package_id' => $package->id])
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');
});

it('returns 404 when downloading without a final revision', function (): void {
    $fixture = ($this->fixture)();
    $package = ($this->package)($fixture);

    $this->actingAs($fixture['manager'])
        ->get(route('packages.pdf.download', $package))
        ->assertNotFound();
});

it('downloads the stored final PDF', function (): void {
    $fixture = ($this->fixture)();
    $package = ($this->package)($fixture);
    $path = 'pdfs/'.$package->id.'/final_R1_1.pdf';
    Storage::disk('private')->put($path, 'pdf-binary');

    PackageRevision::factory()->create([
        'closeout_package_id' => $package->id,
        'storage_path' => $path,
        'revision_label' => 'R1.1',
    ]);

    $this->actingAs($fixture['manager'])
        ->get(route('packages.pdf.download', $package))
        ->assertOk();
});

it('downloads the stored partial PDF via the partial-download route', function (): void {
    $fixture = ($this->fixture)();
    $package = ($this->package)($fixture);
    $path = 'temp/partial_R1_1.pdf';
    Storage::disk('private')->put($path, 'pdf-binary');

    PackageRevision::factory()->create([
        'closeout_package_id' => $package->id,
        'type' => PdfType::PARTIAL,
        'storage_path' => $path,
        'revision_label' => 'R1.1',
    ]);

    $this->actingAs($fixture['manager'])
        ->get(route('packages.pdf.partial-download', $package))
        ->assertOk();
});

it('downloads a specific historical revision via the revision-download route', function (): void {
    $fixture = ($this->fixture)();
    $package = ($this->package)($fixture);
    $path = 'pdfs/'.$package->id.'/final_R1_0.pdf';
    Storage::disk('private')->put($path, 'pdf-binary');

    $revision = PackageRevision::factory()->create([
        'closeout_package_id' => $package->id,
        'type' => PdfType::FINAL,
        'storage_path' => $path,
        'revision_label' => 'R1.0',
    ]);

    $this->actingAs($fixture['manager'])
        ->get(route('packages.pdf.revision-download', [$package, $revision]))
        ->assertOk();
});

it('names the downloaded final PDF after the project, revision and date', function (): void {
    $fixture = ($this->fixture)();
    $fixture['project']->update(['name' => 'Brrancca', 'ps_number' => '17676316']);
    $package = ($this->package)($fixture);
    $path = 'pdfs/'.$package->id.'/final_R2_0.pdf';
    Storage::disk('private')->put($path, 'pdf-binary');

    PackageRevision::factory()->create([
        'closeout_package_id' => $package->id,
        'storage_path' => $path,
        'revision_major' => 2,
        'revision_minor' => 0,
        'revision_label' => 'R2.0',
        'revision_date' => '2026-09-20',
    ]);

    $this->actingAs($fixture['manager'])
        ->get(route('packages.pdf.download', $package))
        ->assertOk()
        ->assertHeader('content-disposition', 'inline; filename="17676316-Brrancca-COP-09-20-2026-Rev2.0.pdf"');
});

it('removes spaces from the project name in the filename', function (): void {
    $fixture = ($this->fixture)();
    $fixture['project']->update(['name' => 'Lomas Peak 12', 'ps_number' => 'PS 9']);
    $package = ($this->package)($fixture);
    $path = 'pdfs/'.$package->id.'/final_R1_1.pdf';
    Storage::disk('private')->put($path, 'pdf-binary');

    PackageRevision::factory()->create([
        'closeout_package_id' => $package->id,
        'storage_path' => $path,
        'revision_major' => 1,
        'revision_minor' => 1,
        'revision_label' => 'R1.1',
        'revision_date' => '2026-09-20',
    ]);

    $this->actingAs($fixture['manager'])
        ->get(route('packages.pdf.download', $package))
        ->assertOk()
        ->assertHeader('content-disposition', 'inline; filename="PS-9-Lomas-Peak-12-COP-09-20-2026-Rev1.1.pdf"');
});

it('renames the file when the configured format changes', function (): void {
    config(['cop.pdf_filename_template' => '{project}_R{major}.{minor}_{date}']);

    $fixture = ($this->fixture)();
    $fixture['project']->update(['name' => 'Lomas Peak']);
    $package = ($this->package)($fixture);
    $path = 'pdfs/'.$package->id.'/final_R1_0.pdf';
    Storage::disk('private')->put($path, 'pdf-binary');

    PackageRevision::factory()->create([
        'closeout_package_id' => $package->id,
        'storage_path' => $path,
        'revision_major' => 1,
        'revision_minor' => 0,
        'revision_label' => 'R1.0',
        'revision_date' => '2026-09-20',
    ]);

    $this->actingAs($fixture['manager'])
        ->get(route('packages.pdf.download', $package))
        ->assertOk()
        ->assertHeader('content-disposition', 'inline; filename="Lomas-Peak_R1.0_09-20-2026.pdf"');
});

it('names the partial PDF download the same way', function (): void {
    $fixture = ($this->fixture)();
    $fixture['project']->update(['name' => 'Brrancca', 'ps_number' => '17676316']);
    $package = ($this->package)($fixture);
    $path = 'temp/partial_R2_0.pdf';
    Storage::disk('private')->put($path, 'pdf-binary');

    PackageRevision::factory()->create([
        'closeout_package_id' => $package->id,
        'type' => PdfType::PARTIAL,
        'storage_path' => $path,
        'revision_major' => 2,
        'revision_minor' => 0,
        'revision_label' => 'R2.0',
        'revision_date' => '2026-09-20',
    ]);

    $this->actingAs($fixture['manager'])
        ->get(route('packages.pdf.partial-download', $package))
        ->assertOk()
        ->assertHeader('content-disposition', 'inline; filename="17676316-Brrancca-COP-09-20-2026-Rev2.0.pdf"');
});

it('returns 404 when downloading a revision that belongs to another package', function (): void {
    $fixture = ($this->fixture)();
    $packageA = ($this->package)($fixture);
    $packageB = ($this->package)($fixture);
    $path = 'pdfs/'.$packageB->id.'/final_R1_0.pdf';
    Storage::disk('private')->put($path, 'pdf-binary');

    $revision = PackageRevision::factory()->create([
        'closeout_package_id' => $packageB->id,
        'storage_path' => $path,
        'revision_label' => 'R1.0',
    ]);

    $this->actingAs($fixture['manager'])
        ->get(route('packages.pdf.revision-download', [$packageA, $revision]))
        ->assertNotFound();
});

it('shows the revision history on the package review page', function (): void {
    $this->withoutVite();

    $fixture = ($this->fixture)();
    $package = ($this->package)($fixture);
    Photo::factory()->create(['closeout_package_id' => $package->id]);
    $path = 'temp/partial_R1_0.pdf';
    Storage::disk('private')->put($path, 'pdf-binary');

    PackageRevision::factory()->create([
        'closeout_package_id' => $package->id,
        'type' => PdfType::PARTIAL,
        'storage_path' => $path,
        'revision_label' => 'R1.0',
        'generated_by' => $fixture['manager']->id,
        'generated_at' => now(),
    ]);

    $this->actingAs($fixture['manager'])
        ->get(route('packages.show', $package))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Packages/Review')
            ->has('revisions', 1)
            ->where('revisions.0.revisionNumber', 'R1.0')
            ->where('revisions.0.generatedBy', $fixture['manager']->name)
            ->where('revisions.0.generatedAt', fn (mixed $value): bool => is_string($value) && strtotime($value) !== false
            ));
});

it('stores a page count above the old tinyint limit on revisions', function (): void {
    $fixture = ($this->fixture)();
    $package = ($this->package)($fixture);

    $revision = PackageRevision::factory()->create([
        'closeout_package_id' => $package->id,
        'total_pages' => 300,
    ]);

    expect($revision->refresh()->total_pages)->toBe(300);
});

it('shows the PDF status page with real progress details', function (): void {
    $this->withoutVite();

    $fixture = ($this->fixture)();
    $package = ($this->package)($fixture);

    $revision = PackageRevision::factory()->create([
        'closeout_package_id' => $package->id,
        'type' => PdfType::FINAL,
        'status' => PdfRevisionStatus::Ready,
        'total_pages' => 12,
        'photo_count' => 20,
    ]);

    $this->actingAs($fixture['manager'])
        ->get(route('packages.pdf.status', $package))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Packages/PdfStatus')
            ->where('isReady', true)
            ->where('progress', 100)
            ->where('revision.total_pages', 12)
            ->where('revision.photo_count', 20));
});

it('returns PDF status JSON for polling requests', function (): void {
    $fixture = ($this->fixture)();
    $package = ($this->package)($fixture);

    PackageRevision::factory()->create([
        'closeout_package_id' => $package->id,
        'type' => PdfType::FINAL,
        'status' => PdfRevisionStatus::Pending,
        'total_pages' => 8,
        'photo_count' => 16,
    ]);

    $this->actingAs($fixture['manager'])
        ->getJson(route('packages.pdf.status', $package))
        ->assertOk()
        ->assertJson([
            'isReady' => false,
            'isGenerating' => true,
            'isFailed' => false,
            'progress' => 10,
            'revision' => [
                'status' => 'pending',
                'total_pages' => 8,
                'photo_count' => 16,
            ],
        ]);
});
