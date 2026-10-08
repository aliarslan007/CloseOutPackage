<?php

use App\Domains\Approval\DTOs\ReadinessReport;
use App\Domains\Approval\Services\ApprovalReadinessService;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use App\Domains\CloseoutPackages\Models\PackageChecklistResponse;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\Markets\Models\Market;
use App\Domains\Photos\Enums\PhotoStatus;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Models\ChecklistItem;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplatePictureCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->readyPackageFixture = function (): array {
        $market = Market::factory()->create();
        $project = Project::factory()->create(['market_id' => $market->id]);
        $template = Template::factory()->create(['market_id' => $market->id]);
        $package = CloseoutPackage::factory()->create([
            'project_id' => $project->id,
            'template_id' => $template->id,
        ]);

        return compact('market', 'project', 'template', 'package');
    };

    $this->packageWithStage = function (array $fixture, string $stage): PackageStage {
        return PackageStage::factory()->create([
            'closeout_package_id' => $fixture['package']->id,
            'stage' => $stage,
        ]);
    };

    $this->check = fn (CloseoutPackage $package): ReadinessReport => app(ApprovalReadinessService::class)->check($package->fresh());

    $this->completeBasics = function (array $fixture): void {
        $checklist = ChecklistItem::factory()->create(['template_id' => $fixture['template']->id]);
        PackageChecklistResponse::factory()->create([
            'closeout_package_id' => $fixture['package']->id,
            'checklist_item_id' => $checklist->id,
            'answer' => true,
        ]);

        $fixture['package']->siteInfo()->create([
            'site_name' => 'Site',
            'address' => '123 Main St',
            'complete_date' => '2026-08-01',
            'vzw_engineer_name' => 'Engineer',
            'vzw_engineer_email' => 'eng@verizon.example',
            'author_name' => 'Author',
            'author_email' => 'author@example.com',
            'pm_name' => 'Manager',
            'pm_email' => 'manager@example.com',
        ]);
    };
});

it('reports the package ready when every rule passes', function (): void {
    $fixture = ($this->readyPackageFixture)();
    $stage = ($this->packageWithStage)($fixture, 'pre_construction');
    $selection = PackageCategorySelection::factory()->create(['package_stage_id' => $stage->id, 'picture_count' => 1]);
    Photo::factory()->create([
        'closeout_package_id' => $fixture['package']->id,
        'package_stage_id' => $stage->id,
        'package_category_selection_id' => $selection->id,
        'status' => PhotoStatus::APPROVED,
        'is_approved' => true,
    ]);

    PackageChecklistResponse::factory()->create([
        'closeout_package_id' => $fixture['package']->id,
        'checklist_item_id' => ChecklistItem::factory()->create(['template_id' => $fixture['template']->id])->id,
        'answer' => true,
    ]);

    $fixture['package']->siteInfo()->create([
        'site_name' => 'Site',
        'address' => '123 Main St',
        'complete_date' => '2026-08-01',
        'vzw_engineer_name' => 'Engineer',
        'vzw_engineer_email' => 'eng@verizon.example',
        'author_name' => 'Author',
        'author_email' => 'author@example.com',
        'pm_name' => 'Manager',
        'pm_email' => 'manager@example.com',
    ]);

    $report = ($this->check)($fixture['package']);

    expect($report->isReady)->toBeTrue()
        ->and($report->failures)->toBe([]);
});

it('fails on missing site information', function (): void {
    $fixture = ($this->readyPackageFixture)();

    $report = ($this->check)($fixture['package']);

    $failure = collect($report->failures)->firstWhere('rule', 'site_info_complete');

    expect($report->isReady)->toBeFalse()
        ->and($failure)->not->toBeNull()
        ->and($failure->context['missing_fields'])->toContain('complete_date');
});

it('does not require a VZW customer engineer to be on file', function (): void {
    $fixture = ($this->readyPackageFixture)();

    // A project with no customer engineer is legitimate — the engineer is only
    // filled in once the customer names one, so it must not block approval.
    $report = ($this->check)($fixture['package']);

    $failure = collect($report->failures)->firstWhere('rule', 'site_info_complete');

    expect($failure->context['missing_fields'])
        ->not->toContain('vzw_engineer_name')
        ->not->toContain('vzw_engineer_email');
});

it('fails on unanswered checklist items', function (): void {
    $fixture = ($this->readyPackageFixture)();
    $checklist = ChecklistItem::factory()->create(['template_id' => $fixture['template']->id, 'label' => 'Site access?']);
    PackageChecklistResponse::factory()->create([
        'closeout_package_id' => $fixture['package']->id,
        'checklist_item_id' => $checklist->id,
        'answer' => null,
    ]);

    $fixture['package']->siteInfo()->create([
        'site_name' => 'Site',
        'address' => '123 Main St',
        'complete_date' => '2026-08-01',
        'vzw_engineer_name' => 'Engineer',
        'vzw_engineer_email' => 'eng@verizon.example',
        'author_name' => 'Author',
        'author_email' => 'author@example.com',
        'pm_name' => 'Manager',
        'pm_email' => 'manager@example.com',
    ]);

    $report = ($this->check)($fixture['package']);

    expect($report->isReady)->toBeFalse()
        ->and($report->failures[0]->rule)->toBe('checklist_answered')
        ->and($report->failures[0]->context['unanswered_items'][0]['label'])->toBe('Site access?');
});

it('fails when a selected category lacks the required photos', function (): void {
    $fixture = ($this->readyPackageFixture)();
    ($this->completeBasics)($fixture);
    $stage = ($this->packageWithStage)($fixture, 'pre_construction');
    $category = TemplatePictureCategory::factory()->create([
        'template_section_id' => $fixture['template']->sections()->firstOrFail()->id,
        'is_required' => true,
    ]);
    PackageCategorySelection::factory()->create([
        'package_stage_id' => $stage->id,
        'template_picture_category_id' => $category->id,
        'picture_count' => 3,
    ]);

    $report = ($this->check)($fixture['package']);

    expect($report->isReady)->toBeFalse()
        ->and($report->failures[0]->rule)->toBe('photos_uploaded')
        ->and($report->failures[0]->context['required'])->toBe(1)
        ->and($report->failures[0]->context['capacity'])->toBe(3)
        ->and($report->failures[0]->context['uploaded'])->toBe(0);
});

it('ignores selected optional categories when counting required photos', function (): void {
    $fixture = ($this->readyPackageFixture)();
    ($this->completeBasics)($fixture);
    $stage = ($this->packageWithStage)($fixture, 'pre_construction');
    $category = TemplatePictureCategory::factory()->create([
        'template_section_id' => $fixture['template']->sections()->firstOrFail()->id,
        'is_required' => false,
    ]);
    PackageCategorySelection::factory()->create([
        'package_stage_id' => $stage->id,
        'template_picture_category_id' => $category->id,
        'picture_count' => 2,
    ]);

    $report = ($this->check)($fixture['package']);

    expect($report->failures)->not->toContain(fn (ReadinessFailure $failure): bool => $failure->rule === 'photos_uploaded');
});
it('ignores soft-deleted categories when counting required photos even if originally marked required', function (): void {
    $fixture = ($this->readyPackageFixture)();
    ($this->completeBasics)($fixture);
    $stage = ($this->packageWithStage)($fixture, 'pre_construction');
    $category = TemplatePictureCategory::factory()->create([
        'template_section_id' => $fixture['template']->sections()->firstOrFail()->id,
        'is_required' => true,
    ]);
    PackageCategorySelection::factory()->create([
        'package_stage_id' => $stage->id,
        'template_picture_category_id' => $category->id,
        'picture_count' => 1,
    ]);

    // Admin deletes the category from the template
    $category->delete();

    $report = ($this->check)($fixture['package']);

    expect($report->failures)->not->toContain(fn (ReadinessFailure $failure): bool => $failure->rule === 'photos_uploaded');
});

it('fails on photos with a pending replacement request', function (): void {
    $fixture = ($this->readyPackageFixture)();
    $stage = ($this->packageWithStage)($fixture, 'pre_construction');
    ($this->completeBasics)($fixture);
    $selection = PackageCategorySelection::factory()->create(['package_stage_id' => $stage->id]);
    Photo::factory()->create([
        'closeout_package_id' => $fixture['package']->id,
        'package_stage_id' => $stage->id,
        'package_category_selection_id' => $selection->id,
        'status' => PhotoStatus::REPLACEMENT_REQUESTED,
    ]);

    $report = ($this->check)($fixture['package']);

    expect($report->isReady)->toBeFalse()
        ->and($report->failures[0]->rule)->toBe('no_pending_replacements')
        ->and($report->failures[0]->context['photo_id'])->not->toBeNull();
});

it('fails on photos that are still flagged', function (): void {
    $fixture = ($this->readyPackageFixture)();
    $stage = ($this->packageWithStage)($fixture, 'pre_construction');
    ($this->completeBasics)($fixture);
    $selection = PackageCategorySelection::factory()->create(['package_stage_id' => $stage->id]);
    Photo::factory()->create([
        'closeout_package_id' => $fixture['package']->id,
        'package_stage_id' => $stage->id,
        'package_category_selection_id' => $selection->id,
        'status' => PhotoStatus::FLAGGED,
    ]);

    $report = ($this->check)($fixture['package']);

    expect($report->isReady)->toBeFalse()
        ->and($report->failures[0]->rule)->toBe('no_pending_replacements')
        ->and($report->failures[0]->context['photo_id'])->not->toBeNull();
});

it('ignores flagged photos that have already been replaced', function (): void {
    $fixture = ($this->readyPackageFixture)();
    $stage = ($this->packageWithStage)($fixture, 'pre_construction');
    ($this->completeBasics)($fixture);
    $selection = PackageCategorySelection::factory()->create(['package_stage_id' => $stage->id]);
    Photo::factory()->create([
        'closeout_package_id' => $fixture['package']->id,
        'package_stage_id' => $stage->id,
        'package_category_selection_id' => $selection->id,
        'status' => PhotoStatus::FLAGGED,
        'replaced_at' => now(),
    ]);

    $report = ($this->check)($fixture['package']);

    expect($report->failures)->not->toContain(fn (ReadinessFailure $failure): bool => $failure->rule === 'no_pending_replacements');
});

it('ignores replacement-requested photos that have already been replaced', function (): void {
    $fixture = ($this->readyPackageFixture)();
    $stage = ($this->packageWithStage)($fixture, 'pre_construction');
    ($this->completeBasics)($fixture);
    $selection = PackageCategorySelection::factory()->create(['package_stage_id' => $stage->id]);
    Photo::factory()->create([
        'closeout_package_id' => $fixture['package']->id,
        'package_stage_id' => $stage->id,
        'package_category_selection_id' => $selection->id,
        'status' => PhotoStatus::REPLACEMENT_REQUESTED,
        'replaced_at' => now(),
    ]);

    $report = ($this->check)($fixture['package']);

    expect($report->failures)->not->toContain(fn (ReadinessFailure $failure): bool => $failure->rule === 'no_pending_replacements');
});

it('fails on photos that have not been reviewed yet', function (): void {
    $fixture = ($this->readyPackageFixture)();
    $stage = ($this->packageWithStage)($fixture, 'pre_construction');
    ($this->completeBasics)($fixture);
    $selection = PackageCategorySelection::factory()->create(['package_stage_id' => $stage->id]);
    Photo::factory()->create([
        'closeout_package_id' => $fixture['package']->id,
        'package_stage_id' => $stage->id,
        'package_category_selection_id' => $selection->id,
        'status' => PhotoStatus::PENDING,
    ]);

    $report = ($this->check)($fixture['package']);

    expect($report->isReady)->toBeFalse()
        ->and($report->failures[0]->rule)->toBe('all_photos_reviewed');
});

it('ignores rejected photos when counting uploaded photos', function (): void {
    $fixture = ($this->readyPackageFixture)();
    $stage = ($this->packageWithStage)($fixture, 'pre_construction');
    ($this->completeBasics)($fixture);
    $category = TemplatePictureCategory::factory()->create([
        'template_section_id' => $fixture['template']->sections()->firstOrFail()->id,
        'is_required' => true,
    ]);
    $selection = PackageCategorySelection::factory()->create([
        'package_stage_id' => $stage->id,
        'template_picture_category_id' => $category->id,
        'picture_count' => 1,
    ]);
    Photo::factory()->create([
        'closeout_package_id' => $fixture['package']->id,
        'package_stage_id' => $stage->id,
        'package_category_selection_id' => $selection->id,
        'status' => PhotoStatus::REJECTED,
    ]);

    $report = ($this->check)($fixture['package']);

    expect($report->isReady)->toBeFalse()
        ->and($report->failures[0]->rule)->toBe('photos_uploaded');
});
