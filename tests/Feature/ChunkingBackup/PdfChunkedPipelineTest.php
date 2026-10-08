<?php

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Markets\Models\Market;
use App\Domains\PdfGeneration\Enums\PdfRevisionStatus;
use App\Domains\PdfGeneration\Jobs\GeneratePdfJob;
use App\Domains\PdfGeneration\Jobs\PrepareChunkedPdfJob;
use App\Domains\PdfGeneration\Models\PackageRevision;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Models\Template;
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

    $this->fixture = function (): array {
        $market = Market::factory()->create();
        $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
        $manager->markets()->attach($market->id);
        $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
        $template = Template::factory()->create(['market_id' => $market->id]);

        return compact('market', 'manager', 'project', 'template');
    };

    $this->package = function (array $fixture, string $status = PackageStatus::APPROVED->value): CloseoutPackage {
        return CloseoutPackage::factory()->create([
            'project_id' => $fixture['project']->id,
            'template_id' => $fixture['template']->id,
            'status' => $status,
        ]);
    };
});

it('dispatches the legacy job by default', function (): void {
    Queue::fake();
    config(['cop.pdf.strategy' => 'legacy']);

    $fixture = ($this->fixture)();
    $package = ($this->package)($fixture);
    Photo::factory()->create(['closeout_package_id' => $package->id]);

    $this->actingAs($fixture['manager'])
        ->post(route('manager.packages.pdf.final', $package), ['closeout_package_id' => $package->id])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Queue::assertPushed(GeneratePdfJob::class);
    Queue::assertNotPushed(PrepareChunkedPdfJob::class);
});

it('dispatches the chunked pipeline when the strategy flag is set', function (): void {
    Queue::fake();
    config(['cop.pdf.strategy' => 'chunked']);

    $fixture = ($this->fixture)();
    $package = ($this->package)($fixture);
    Photo::factory()->create(['closeout_package_id' => $package->id]);

    $this->actingAs($fixture['manager'])
        ->post(route('manager.packages.pdf.final', $package), ['closeout_package_id' => $package->id])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Queue::assertPushed(PrepareChunkedPdfJob::class);
    Queue::assertNotPushed(GeneratePdfJob::class);
});

it('dispatches the chunked pipeline for progress PDFs with the email payload', function (): void {
    Queue::fake();
    config(['cop.pdf.strategy' => 'chunked']);

    $fixture = ($this->fixture)();
    $package = ($this->package)($fixture, PackageStatus::DRAFT->value);
    Photo::factory()->create(['closeout_package_id' => $package->id]);

    $this->actingAs($fixture['manager'])
        ->post(route('manager.packages.pdf.partial', $package), [
            'closeout_package_id' => $package->id,
            'email_to' => 'engineer@verizon.example',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Queue::assertPushed(function (PrepareChunkedPdfJob $job): bool {
        return $job->type->value === 'partial' && $job->emailTo === 'engineer@verizon.example';
    });
});

it('closes out stale pending revisions before starting a new chunked pipeline', function (): void {
    config(['cop.pdf.strategy' => 'chunked']);
    Queue::fake();

    $fixture = ($this->fixture)();
    $package = ($this->package)($fixture);
    Photo::factory()->create(['closeout_package_id' => $package->id]);

    $stale = PackageRevision::factory()->create([
        'closeout_package_id' => $package->id,
        'status' => PdfRevisionStatus::Pending,
    ]);

    $this->actingAs($fixture['manager'])
        ->post(route('manager.packages.pdf.final', $package), ['closeout_package_id' => $package->id])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($stale->refresh()->status)->toBe(PdfRevisionStatus::Failed);
});
