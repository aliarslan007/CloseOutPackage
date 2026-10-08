<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Markets\Models\Market;
use App\Domains\PdfGeneration\Services\BrowsershotPdfService;
use App\Domains\Projects\Models\Project;
use App\Domains\ScopeOfWork\Actions\GenerateSowPdfAction;
use App\Domains\ScopeOfWork\Enums\SowPdfGenerationStatus;
use App\Domains\ScopeOfWork\Enums\SowStatus;
use App\Domains\ScopeOfWork\Jobs\GenerateSowPdfJob;
use App\Domains\ScopeOfWork\Models\SowChecklistItem;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\ScopeOfWork\Notifications\SowPdfReadyNotification;
use App\Domains\ScopeOfWork\Notifications\SowReportPublishedNotification;
use App\Domains\ScopeOfWork\Notifications\SowReportReopenedNotification;
use App\Domains\ScopeOfWork\Notifications\SowReportSubmittedNotification;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Storage::fake('private');
});

function sowTestEnvironment(): array
{
    $market = Market::factory()->create(['is_active' => true]);

    $manager = User::factory()->create(['name' => 'John Manager'])->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);

    $technician = User::factory()->create(['name' => 'Bob Technician'])->assignRole(Role::FIELD_SUPERVISOR->value);
    $technician->markets()->attach($market->id);

    $project = Project::factory()->create([
        'market_id' => $market->id,
        'manager_id' => $manager->id,
    ]);

    $report = SowReport::create([
        'market_id' => $market->id,
        'created_by' => $technician->id,
        'project_id' => $project->id,
        'status' => SowStatus::DRAFT,
        'title' => 'Alpha SOW',
        'site_name' => 'Site Alpha',
        'revision' => 1,
    ]);

    return compact('market', 'manager', 'technician', 'project', 'report');
}

it('enforces 1000 character limit on survey checklist explanation', function (): void {
    extract(sowTestEnvironment());

    $item = SowChecklistItem::create([
        'sow_report_id' => $report->id,
        'label' => 'Is fiber demarc tagged?',
        'is_required' => false,
        'has_explain_field' => true,
        'sort_order' => 1,
    ]);

    // 1000 chars should be accepted
    $this->actingAs($technician)
        ->putJson(route('sow.reports.checklist.update', $report->id), [
            'responses' => [
                [
                    'sow_checklist_item_id' => $item->id,
                    'response' => 'no',
                    'explanation' => str_repeat('a', 1000),
                ],
            ],
        ])
        ->assertOk();

    // 1001 chars should fail validation
    $this->actingAs($technician)
        ->putJson(route('sow.reports.checklist.update', $report->id), [
            'responses' => [
                [
                    'sow_checklist_item_id' => $item->id,
                    'response' => 'no',
                    'explanation' => str_repeat('a', 1001),
                ],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('responses.0.explanation');
});

it('allows a manager to reopen an SOW with notes and restores technician edit rights', function (): void {
    Notification::fake();
    extract(sowTestEnvironment());

    $report->update(['status' => SowStatus::IN_REVIEW, 'submitted_at' => now()]);

    // Manager reopens report with instructions for technician
    $response = $this->actingAs($manager)
        ->post(route('sow.reports.reopen', $report->id), [
            'notes' => 'Please provide a clearer photo of demarc equipment and explain question #1.',
        ]);

    $response->assertRedirect();
    $report->refresh();

    expect($report->status)->toBe(SowStatus::DRAFT)
        ->and($report->reopen_notes)->toBe('Please provide a clearer photo of demarc equipment and explain question #1.')
        ->and($report->reopened_by)->toBe($manager->id)
        ->and($report->reopened_at)->not->toBeNull();

    // Verify technician received notification
    Notification::assertSentTo(
        $technician,
        SowReportReopenedNotification::class,
        fn (SowReportReopenedNotification $n) => $n->toArray($technician)['reopen_notes'] === 'Please provide a clearer photo of demarc equipment and explain question #1.'
    );

    // Technician now has full edit access in DRAFT status
    $this->actingAs($technician)
        ->patch(route('sow.reports.update', $report->id), [
            'scope_of_work_description' => 'Updated by technician after manager feedback',
        ])
        ->assertRedirect();

    $report->refresh();
    expect($report->scope_of_work_description)->toBe('Updated by technician after manager feedback');
});

it('dispatches async GenerateSowPdfJob on publish and queues notification', function (): void {
    Queue::fake();
    Notification::fake();
    extract(sowTestEnvironment());

    $report->update(['status' => SowStatus::IN_REVIEW]);

    $this->actingAs($manager)
        ->post(route('sow.reports.publish', $report->id))
        ->assertRedirect();

    Queue::assertPushed(GenerateSowPdfJob::class, function (GenerateSowPdfJob $job) use ($report): bool {
        return $job->report->id === $report->id;
    });

    Notification::assertSentTo($technician, SowReportPublishedNotification::class);
});

it('increments revision when reopened report is regenerated', function (): void {
    Queue::fake();
    extract(sowTestEnvironment());

    $report->update([
        'status' => SowStatus::PUBLISHED,
        'revision' => 1,
        'reopened_at' => now(),
    ]);

    $this->actingAs($manager)
        ->post(route('sow.reports.pdf.regenerate', $report->id))
        ->assertRedirect();

    $report->refresh();
    expect($report->revision)->toBe(2)
        ->and($report->reopened_at)->toBeNull()
        ->and($report->pdf_generation_status)->toBe(SowPdfGenerationStatus::GENERATING);

    Queue::assertPushed(GenerateSowPdfJob::class);
});

it('denies field technicians from regenerating PDFs while allowing download', function (): void {
    extract(sowTestEnvironment());

    $report->update(['status' => SowStatus::PUBLISHED]);

    // Regenerate forbidden for field technician
    $this->actingAs($technician)
        ->postJson(route('sow.reports.pdf.regenerate', $report->id))
        ->assertForbidden();

    // Mock Browsershot for download
    $this->mock(BrowsershotPdfService::class, function ($mock): void {
        $mock->shouldReceive('generate')->once()->andReturn('%PDF-1.4 Mock Binary%');
    });

    // Download allowed for field technician
    $this->actingAs($technician)
        ->get(route('sow.reports.pdf', $report->id))
        ->assertOk();
});

it('executes GenerateSowPdfJob and sends SowPdfReadyNotification', function (): void {
    Notification::fake();
    extract(sowTestEnvironment());

    $this->mock(BrowsershotPdfService::class, function ($mock): void {
        $mock->shouldReceive('generate')->once()->andReturn('%PDF-1.4 Mock Binary%');
    });

    $job = new GenerateSowPdfJob($report, $manager->id);
    $job->handle(app(GenerateSowPdfAction::class));

    $report->refresh();
    expect($report->pdf_generation_status)->toBe(SowPdfGenerationStatus::READY);

    Notification::assertSentTo($technician, SowPdfReadyNotification::class);
    Notification::assertSentTo($manager, SowPdfReadyNotification::class);
});

it('sends SowReportSubmittedNotification to managers when technician submits', function (): void {
    Notification::fake();
    extract(sowTestEnvironment());

    $this->actingAs($technician)
        ->post(route('sow.reports.submit', $report->id))
        ->assertRedirect();

    Notification::assertSentTo(
        $manager,
        SowReportSubmittedNotification::class,
        fn (SowReportSubmittedNotification $n) => ! $n->toArray($manager)['is_resubmission']
    );
});
