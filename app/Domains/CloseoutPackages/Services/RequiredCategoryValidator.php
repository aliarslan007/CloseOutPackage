<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Services;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\Templates\Enums\StageType;
use App\Domains\Templates\Models\TemplatePictureCategory;
use App\Domains\Templates\Models\TemplateSection;
use App\Shared\Exceptions\RequiredCategoryIncompleteException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Enforces that every category the live template marks required is included in
 * the package with at least one uploaded photo.
 *
 * Packages snapshot the required flag at creation (see PackageInitializationService),
 * so this reads the live template to catch a category an admin made required
 * after the package already existed.
 */
final class RequiredCategoryValidator
{
    /**
     * Pass a stage to validate that stage (stage submit); omit it to validate
     * every stage of the package (package submit).
     */
    public function assertSatisfied(CloseoutPackage $package, ?PackageStage $stage = null): void
    {
        $requiredCategories = $this->requiredCategories($package, $stage?->stage);

        if ($requiredCategories->isEmpty()) {
            return;
        }

        /** @var list<int> $stageIds */
        $stageIds = $stage !== null
            ? [$stage->id]
            : $package->stages()->pluck('id')->all();

        $selections = PackageCategorySelection::query()
            ->whereIn('package_stage_id', $stageIds)
            ->withCount(['photos as active_photo_count' => function (Builder $query): void {
                $query->whereNull('replaced_at');
            }])
            ->get()
            ->keyBy('template_picture_category_id');

        $missing = [];

        foreach ($requiredCategories as $category) {
            $selection = $selections->get($category->id);

            if (
                ! $selection instanceof PackageCategorySelection
                || ! $selection->is_selected
                || $selection->active_photo_count < 1
            ) {
                $missing[] = $category->name;
            }
        }

        if ($missing !== []) {
            throw new RequiredCategoryIncompleteException($missing);
        }
    }

    /**
     * @return Collection<int, TemplatePictureCategory>
     */
    private function requiredCategories(CloseoutPackage $package, ?StageType $stageType): Collection
    {
        $template = $package->template()->with('sections.categories')->first();

        if ($template === null) {
            return new Collection;
        }

        return $template->sections
            ->filter(
                fn (TemplateSection $section): bool => $stageType === null || $section->stage === $stageType
            )
            ->flatMap(fn (TemplateSection $section): Collection => $section->categories)
            ->filter(fn (TemplatePictureCategory $category): bool => $category->is_required)
            ->values();
    }
}
