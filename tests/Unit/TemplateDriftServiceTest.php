<?php

use App\Domains\CloseoutPackages\DTOs\DriftConflict;
use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use App\Domains\CloseoutPackages\Models\PackageChecklistResponse;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\CloseoutPackages\Services\TemplateDriftService;
use App\Domains\Photos\Models\Photo;
use App\Domains\Templates\Enums\StageType;
use App\Domains\Templates\Models\ChecklistItem;
use App\Domains\Templates\Models\TemplatePictureCategory;
use App\Shared\Exceptions\TemplateDriftConflictException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->driftPackage = function (int $templateBucket, int $selectionCount): array {
        $package = CloseoutPackage::factory()->create(['status' => PackageStatus::DRAFT]);
        $stage = PackageStage::factory()->create([
            'closeout_package_id' => $package->id,
            'stage' => StageType::PRE_CONSTRUCTION,
        ]);
        $category = TemplatePictureCategory::factory()->create(['default_picture_count' => $templateBucket]);
        $selection = PackageCategorySelection::factory()->create([
            'package_stage_id' => $stage->id,
            'template_picture_category_id' => $category->id,
            'picture_count' => $selectionCount,
        ]);

        return compact('package', 'stage', 'category', 'selection');
    };

    $this->uploadPhotos = function (array $fixture, int $count, bool $replaced = false): void {
        for ($position = 1; $position <= $count; $position++) {
            Photo::factory()->create([
                'closeout_package_id' => $fixture['package']->id,
                'package_stage_id' => $fixture['stage']->id,
                'package_category_selection_id' => $fixture['selection']->id,
                'position' => $position,
                'replaced_at' => $replaced ? now() : null,
            ]);
        }
    };
});

it('returns a clean report when the template matches the snapshot', function (): void {
    $fixture = ($this->driftPackage)(5, 5);
    ($this->uploadPhotos)($fixture, 4);

    $report = app(TemplateDriftService::class)->detect($fixture['package']);

    expect($report->hasUnsafeDrift)->toBeFalse()
        ->and($report->conflicts)->toBe([]);
});

it('returns a clean report for a non-draft package', function (): void {
    $fixture = ($this->driftPackage)(1, 10);
    ($this->uploadPhotos)($fixture, 8);
    $fixture['package']->update(['status' => PackageStatus::APPROVED]);

    $report = app(TemplateDriftService::class)->detect($fixture['package']->fresh());

    expect($report->hasUnsafeDrift)->toBeFalse();
});

it('flags a bucket decrease only when uploads exceed the new limit', function (): void {
    $service = app(TemplateDriftService::class);

    $withinLimit = ($this->driftPackage)(5, 10);
    ($this->uploadPhotos)($withinLimit, 5);
    expect($service->detect($withinLimit['package'])->hasUnsafeDrift)->toBeFalse();

    $overLimit = ($this->driftPackage)(5, 10);
    ($this->uploadPhotos)($overLimit, 8);
    $report = $service->detect($overLimit['package']);

    expect($report->hasUnsafeDrift)->toBeTrue()
        ->and($report->conflicts)->toHaveCount(1);

    $conflict = $report->conflicts[0];
    expect($conflict)
        ->type->toBe(DriftConflict::TYPE_BUCKET_DECREASED)
        ->stageId->toBe($overLimit['stage']->id)
        ->stageName->toBe('Pre Construction')
        ->categoryId->toBe($overLimit['category']->id)
        ->categoryName->toBe($overLimit['category']->name)
        ->newValue->toBe(5)
        ->currentPhotoCount->toBe(8);
});

it('returns a clean report when the bucket increased', function (): void {
    $fixture = ($this->driftPackage)(10, 5);
    ($this->uploadPhotos)($fixture, 5);

    expect(app(TemplateDriftService::class)->detect($fixture['package'])->hasUnsafeDrift)->toBeFalse();
});

it('ignores replaced photos when counting uploads', function (): void {
    $fixture = ($this->driftPackage)(3, 5);
    ($this->uploadPhotos)($fixture, 3, replaced: true);
    ($this->uploadPhotos)($fixture, 2);

    expect(app(TemplateDriftService::class)->detect($fixture['package'])->hasUnsafeDrift)->toBeFalse();
});

it('raises the picture count up to a larger template bucket', function (): void {
    $fixture = ($this->driftPackage)(10, 5);

    app(TemplateDriftService::class)->applySafeChanges($fixture['package']);

    expect($fixture['selection']->fresh()->picture_count)->toBe(10);
});

it('syncs a shrunk template bucket down onto the package', function (): void {
    $fixture = ($this->driftPackage)(2, 5);

    app(TemplateDriftService::class)->applySafeChanges($fixture['package']);

    expect($fixture['selection']->fresh()->picture_count)->toBe(2);
});

it('heals every stage of the package, not just the saved one', function (): void {
    $fixture = ($this->driftPackage)(2, 5);

    $otherStage = PackageStage::factory()->create([
        'closeout_package_id' => $fixture['package']->id,
        'stage' => StageType::CONSTRUCTION,
    ]);
    $otherCategory = TemplatePictureCategory::factory()->create(['default_picture_count' => 4]);
    $otherSelection = PackageCategorySelection::factory()->create([
        'package_stage_id' => $otherStage->id,
        'template_picture_category_id' => $otherCategory->id,
        'picture_count' => 1,
    ]);

    app(TemplateDriftService::class)->applySafeChanges($fixture['package']);

    expect($fixture['selection']->fresh()->picture_count)->toBe(2)
        ->and($otherSelection->fresh()->picture_count)->toBe(4);
});

it('selects a category the template now makes required', function (): void {
    $fixture = ($this->driftPackage)(5, 5);
    $fixture['selection']->update(['is_selected' => false]);
    $fixture['category']->update(['is_required' => true]);

    app(TemplateDriftService::class)->applySafeChanges($fixture['package']);

    expect($fixture['selection']->fresh()->is_selected)->toBeTrue();
});

it('leaves an optional deselected category unchecked', function (): void {
    $fixture = ($this->driftPackage)(5, 5);
    $fixture['selection']->update(['is_selected' => false]);

    app(TemplateDriftService::class)->applySafeChanges($fixture['package']);

    expect($fixture['selection']->fresh()->is_selected)->toBeFalse();
});

it('clears a stale explanation when the template drops the explain field', function (): void {
    $fixture = ($this->driftPackage)(5, 5);
    $item = ChecklistItem::factory()->create([
        'template_id' => $fixture['package']->template_id,
        'has_explain_field' => false,
    ]);
    $response = PackageChecklistResponse::factory()->create([
        'closeout_package_id' => $fixture['package']->id,
        'checklist_item_id' => $item->id,
        'answer' => false,
        'explain' => 'Legacy explanation',
    ]);

    app(TemplateDriftService::class)->applySafeChanges($fixture['package']);

    expect($response->fresh()->explain)->toBeNull();
});

it('scopes detection to a single stage when asked', function (): void {
    $fixture = ($this->driftPackage)(5, 10);
    ($this->uploadPhotos)($fixture, 8);

    $otherStage = PackageStage::factory()->create([
        'closeout_package_id' => $fixture['package']->id,
        'stage' => StageType::CONSTRUCTION,
    ]);

    $service = app(TemplateDriftService::class);

    expect($service->detect($fixture['package'], $otherStage)->hasUnsafeDrift)->toBeFalse()
        ->and($service->detect($fixture['package'], $fixture['stage'])->hasUnsafeDrift)->toBeTrue();
});

it('throws with the conflict payload when unsafe drift remains', function (): void {
    $fixture = ($this->driftPackage)(5, 10);
    ($this->uploadPhotos)($fixture, 8);

    try {
        app(TemplateDriftService::class)->assertNoUnsafeDrift($fixture['package']);
        $this->fail('Expected TemplateDriftConflictException was not thrown.');
    } catch (TemplateDriftConflictException $exception) {
        expect($exception->conflicts)->toHaveCount(1)
            ->and($exception->conflicts[0]->type)->toBe(DriftConflict::TYPE_BUCKET_DECREASED);
    }
});

it('passes assertion on a clean package', function (): void {
    $fixture = ($this->driftPackage)(5, 5);
    ($this->uploadPhotos)($fixture, 3);

    app(TemplateDriftService::class)->assertNoUnsafeDrift($fixture['package']);

    expect(true)->toBeTrue();
});

it('creates response placeholders for newly added checklist items on safe changes', function (): void {
    $fixture = ($this->driftPackage)(5, 5);
    $package = $fixture['package'];

    $newItem = ChecklistItem::factory()->create([
        'template_id' => $package->template_id,
        'label' => 'New Safety Inspection Item',
    ]);

    expect($package->checklistResponses()->where('checklist_item_id', $newItem->id)->exists())->toBeFalse();

    app(TemplateDriftService::class)->applySafeChanges($package);

    $newResponse = $package->checklistResponses()->where('checklist_item_id', $newItem->id)->first();
    expect($newResponse)->not->toBeNull()
        ->and($newResponse->answer)->toBeNull()
        ->and($newResponse->is_na)->toBeFalse()
        ->and($newResponse->explain)->toBeNull();
});

it('deletes checklist responses when checklist items are trashed or removed', function (): void {
    $fixture = ($this->driftPackage)(5, 5);
    $package = $fixture['package'];

    $item = ChecklistItem::factory()->create([
        'template_id' => $package->template_id,
    ]);
    $response = PackageChecklistResponse::factory()->create([
        'closeout_package_id' => $package->id,
        'checklist_item_id' => $item->id,
        'answer' => true,
    ]);

    $item->delete(); // Soft delete

    app(TemplateDriftService::class)->applySafeChanges($package);

    expect(PackageChecklistResponse::find($response->id))->toBeNull();
});

it('creates category selections when new categories are added to a template section', function (): void {
    $fixture = ($this->driftPackage)(5, 5);
    $package = $fixture['package'];
    $stage = $fixture['stage'];

    $section = $package->template->sections()->where('stage', $stage->stage->value)->firstOrFail();
    $newCategory = TemplatePictureCategory::factory()->create([
        'template_section_id' => $section->id,
        'name' => 'Brand New Sub-Category',
        'default_picture_count' => 3,
        'is_required' => true,
    ]);

    expect($stage->categorySelections()->where('template_picture_category_id', $newCategory->id)->exists())->toBeFalse();

    app(TemplateDriftService::class)->applySafeChanges($package);

    $newSelection = $stage->categorySelections()->where('template_picture_category_id', $newCategory->id)->first();
    expect($newSelection)->not->toBeNull()
        ->and($newSelection->picture_count)->toBe(3)
        ->and($newSelection->is_selected)->toBeTrue();
});

it('resets is_na and answer to null when allows_na is disabled by admin', function (): void {
    $fixture = ($this->driftPackage)(5, 5);
    $package = $fixture['package'];

    $item = ChecklistItem::factory()->create([
        'template_id' => $package->template_id,
        'allows_na' => false,
    ]);
    $response = PackageChecklistResponse::factory()->create([
        'closeout_package_id' => $package->id,
        'checklist_item_id' => $item->id,
        'is_na' => true,
        'answer' => null,
    ]);

    app(TemplateDriftService::class)->applySafeChanges($package);

    $fresh = $response->fresh();
    expect($fresh->is_na)->toBeFalse()
        ->and($fresh->answer)->toBeNull();
});
