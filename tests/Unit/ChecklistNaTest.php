<?php

declare(strict_types=1);

use App\Domains\Approval\Services\ApprovalReadinessService;
use App\Domains\CloseoutPackages\Actions\SubmitPackageAction;
use App\Domains\CloseoutPackages\Actions\UpdatePackageChecklistAction;
use App\Domains\CloseoutPackages\DTOs\UpdatePackageChecklistDTO;
use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Enums\StageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageChecklistResponse;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\CloseoutPackages\Services\TemplateDriftService;
use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Actions\CreateChecklistItemAction;
use App\Domains\Templates\Actions\UpdateChecklistItemAction;
use App\Domains\Templates\DTOs\CreateChecklistItemDTO;
use App\Domains\Templates\Models\ChecklistItem;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Services\TemplateCloningService;
use App\Domains\Users\Models\User;
use App\Shared\Exceptions\ChecklistIncompleteException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

it('creates and updates checklist items with allows_na option', function (): void {
    $template = Template::factory()->create();

    $dto = new CreateChecklistItemDTO(
        templateId: $template->id,
        label: 'Safety hazard present?',
        hasExplainField: true,
        sortOrder: 1,
        allowsNa: true,
    );

    $item = app(CreateChecklistItemAction::class)->execute($dto);

    expect($item->allows_na)->toBeTrue()
        ->and($item->has_explain_field)->toBeTrue();

    app(UpdateChecklistItemAction::class)->execute(
        item: $item,
        label: 'Updated safety hazard question',
        hasExplainField: false,
        allowsNa: false,
    );

    $item->refresh();

    expect($item->allows_na)->toBeFalse()
        ->and($item->has_explain_field)->toBeFalse()
        ->and($item->label)->toBe('Updated safety hazard question');
});

it('preserves allows_na flag when cloning a template', function (): void {
    $original = Template::factory()->create();

    ChecklistItem::factory()->create([
        'template_id' => $original->id,
        'label' => 'Standard check',
        'allows_na' => false,
    ]);

    ChecklistItem::factory()->create([
        'template_id' => $original->id,
        'label' => 'Optional technician check',
        'allows_na' => true,
    ]);

    $cloned = app(TemplateCloningService::class)->duplicate($original);

    $items = $cloned->checklistItems()->get()->keyBy('label');

    expect($items['Standard check']->allows_na)->toBeFalse()
        ->and($items['Optional technician check']->allows_na)->toBeTrue();
});

it('updates checklist responses with N/A answer', function (): void {
    $market = Market::factory()->create();
    $project = Project::factory()->create(['market_id' => $market->id]);
    $template = Template::factory()->create(['market_id' => $market->id]);
    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
        'status' => PackageStatus::DRAFT,
    ]);

    $item = ChecklistItem::factory()->create([
        'template_id' => $template->id,
        'allows_na' => true,
    ]);

    $dto = new UpdatePackageChecklistDTO([
        [
            'checklist_item_id' => $item->id,
            'answer' => null,
            'explain' => null,
            'is_na' => true,
        ],
    ]);

    app(UpdatePackageChecklistAction::class)->execute($package, $dto);

    $response = PackageChecklistResponse::query()
        ->where('closeout_package_id', $package->id)
        ->where('checklist_item_id', $item->id)
        ->firstOrFail();

    expect($response->is_na)->toBeTrue()
        ->and($response->answer)->toBeNull()
        ->and($response->explain)->toBeNull();
});

it('allows package submission when N/A is answered on allowed items without requiring explanation', function (): void {
    $market = Market::factory()->create();
    $project = Project::factory()->create(['market_id' => $market->id]);
    $template = Template::factory()->create(['market_id' => $market->id]);
    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
        'status' => PackageStatus::DRAFT,
    ]);

    PackageStage::factory()->create([
        'closeout_package_id' => $package->id,
        'stage' => 'post_completion',
        'status' => StageStatus::SUBMITTED,
    ]);

    $item = ChecklistItem::factory()->create([
        'template_id' => $template->id,
        'has_explain_field' => true,
        'allows_na' => true,
    ]);

    PackageChecklistResponse::factory()->create([
        'closeout_package_id' => $package->id,
        'checklist_item_id' => $item->id,
        'answer' => null,
        'explain' => null,
        'is_na' => true,
    ]);

    $user = User::factory()->create();

    expect(fn () => app(SubmitPackageAction::class)->execute($package, $user))
        ->not->toThrow(ChecklistIncompleteException::class);
});

it('rejects package submission if N/A is answered on an item that disallows N/A', function (): void {
    $market = Market::factory()->create();
    $project = Project::factory()->create(['market_id' => $market->id]);
    $template = Template::factory()->create(['market_id' => $market->id]);
    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
        'status' => PackageStatus::DRAFT,
    ]);

    PackageStage::factory()->create([
        'closeout_package_id' => $package->id,
        'stage' => 'post_completion',
        'status' => StageStatus::SUBMITTED,
    ]);

    $item = ChecklistItem::factory()->create([
        'template_id' => $template->id,
        'label' => 'Strict Item',
        'allows_na' => false,
    ]);

    PackageChecklistResponse::factory()->create([
        'closeout_package_id' => $package->id,
        'checklist_item_id' => $item->id,
        'answer' => null,
        'is_na' => true,
    ]);

    $user = User::factory()->create();

    expect(fn () => app(SubmitPackageAction::class)->execute($package, $user))
        ->toThrow(ChecklistIncompleteException::class);
});

it('readiness service considers N/A as answered when item allows it', function (): void {
    $market = Market::factory()->create();
    $project = Project::factory()->create(['market_id' => $market->id]);
    $template = Template::factory()->create(['market_id' => $market->id]);
    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
    ]);

    $item = ChecklistItem::factory()->create([
        'template_id' => $template->id,
        'allows_na' => true,
    ]);

    PackageChecklistResponse::factory()->create([
        'closeout_package_id' => $package->id,
        'checklist_item_id' => $item->id,
        'answer' => null,
        'is_na' => true,
    ]);

    $service = app(ApprovalReadinessService::class);
    $report = $service->check($package);

    $checklistFailure = collect($report->failures)->firstWhere('rule', 'checklist_answered');
    expect($checklistFailure)->toBeNull();
});

it('safe-heals is_na when template turns off allows_na', function (): void {
    $market = Market::factory()->create();
    $project = Project::factory()->create(['market_id' => $market->id]);
    $template = Template::factory()->create(['market_id' => $market->id]);
    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
    ]);

    $item = ChecklistItem::factory()->create([
        'template_id' => $template->id,
        'allows_na' => false,
    ]);

    $response = PackageChecklistResponse::factory()->create([
        'closeout_package_id' => $package->id,
        'checklist_item_id' => $item->id,
        'is_na' => true,
    ]);

    app(TemplateDriftService::class)->applySafeChanges($package);

    $response->refresh();
    expect($response->is_na)->toBeFalse();
});
