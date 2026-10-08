<?php

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\CloseoutPackages\Services\RequiredCategoryValidator;
use App\Domains\Photos\Models\Photo;
use App\Domains\Templates\Enums\StageType;
use App\Domains\Templates\Models\TemplatePictureCategory;
use App\Shared\Exceptions\RequiredCategoryIncompleteException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->fixture = function (
        bool $required = true,
        bool $selected = true,
        int $photos = 0,
        int $replaced = 0,
    ): array {
        $package = CloseoutPackage::factory()->create(['status' => PackageStatus::DRAFT]);
        $section = $package->template->sections()
            ->where('stage', StageType::PRE_CONSTRUCTION)
            ->firstOrFail();
        $category = TemplatePictureCategory::factory()->create([
            'template_section_id' => $section->id,
            'is_required' => $required,
        ]);
        $stage = PackageStage::factory()->create([
            'closeout_package_id' => $package->id,
            'stage' => StageType::PRE_CONSTRUCTION,
        ]);
        $selection = PackageCategorySelection::factory()->create([
            'package_stage_id' => $stage->id,
            'template_picture_category_id' => $category->id,
            'is_selected' => $selected,
            'picture_count' => 1,
        ]);

        $makePhoto = function (int $position, bool $isReplaced) use ($package, $stage, $selection): void {
            Photo::factory()->create([
                'closeout_package_id' => $package->id,
                'package_stage_id' => $stage->id,
                'package_category_selection_id' => $selection->id,
                'position' => $position,
                'replaced_at' => $isReplaced ? now() : null,
            ]);
        };

        for ($position = 1; $position <= $photos; $position++) {
            $makePhoto($position, false);
        }
        for ($position = $photos + 1; $position <= $photos + $replaced; $position++) {
            $makePhoto($position, true);
        }

        return compact('package', 'category', 'stage', 'selection');
    };
});

it('throws when a required category has no photo', function (): void {
    $fixture = ($this->fixture)(required: true, photos: 0);

    expect(fn () => app(RequiredCategoryValidator::class)->assertSatisfied($fixture['package']))
        ->toThrow(RequiredCategoryIncompleteException::class);
});

it('throws when a required category is deselected', function (): void {
    $fixture = ($this->fixture)(required: true, selected: false, photos: 1);

    expect(fn () => app(RequiredCategoryValidator::class)->assertSatisfied($fixture['package']))
        ->toThrow(RequiredCategoryIncompleteException::class);
});

it('passes when a required category has an active photo', function (): void {
    $fixture = ($this->fixture)(required: true, photos: 1);

    app(RequiredCategoryValidator::class)->assertSatisfied($fixture['package']);

    expect($fixture['selection']->fresh()->is_selected)->toBeTrue();
});

it('ignores replaced photos when counting', function (): void {
    $fixture = ($this->fixture)(required: true, photos: 0, replaced: 2);

    expect(fn () => app(RequiredCategoryValidator::class)->assertSatisfied($fixture['package']))
        ->toThrow(RequiredCategoryIncompleteException::class);
});

it('ignores optional categories', function (): void {
    $fixture = ($this->fixture)(required: false, photos: 0);

    app(RequiredCategoryValidator::class)->assertSatisfied($fixture['package']);

    expect(true)->toBeTrue();
});

it('scopes validation to the submitted stage', function (): void {
    $fixture = ($this->fixture)(required: true, photos: 0);

    $otherStage = PackageStage::factory()->create([
        'closeout_package_id' => $fixture['package']->id,
        'stage' => StageType::CONSTRUCTION,
    ]);
    $otherCategory = TemplatePictureCategory::factory()->create([
        'template_section_id' => $fixture['package']->template->sections()
            ->where('stage', StageType::CONSTRUCTION)
            ->firstOrFail()->id,
        'is_required' => true,
    ]);
    $otherSelection = PackageCategorySelection::factory()->create([
        'package_stage_id' => $otherStage->id,
        'template_picture_category_id' => $otherCategory->id,
        'is_selected' => true,
        'picture_count' => 1,
    ]);
    Photo::factory()->create([
        'closeout_package_id' => $fixture['package']->id,
        'package_stage_id' => $otherStage->id,
        'package_category_selection_id' => $otherSelection->id,
        'position' => 1,
    ]);

    $validator = app(RequiredCategoryValidator::class);

    // The pre-construction violation surfaces there...
    expect(fn () => $validator->assertSatisfied($fixture['package'], $fixture['stage']))
        ->toThrow(RequiredCategoryIncompleteException::class);

    // ...but is irrelevant when validating the construction stage.
    $validator->assertSatisfied($fixture['package'], $otherStage);

    expect(true)->toBeTrue();
});
