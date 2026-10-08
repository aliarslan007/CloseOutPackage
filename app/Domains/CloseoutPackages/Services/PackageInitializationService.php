<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Services;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use App\Domains\CloseoutPackages\Models\PackageChecklistResponse;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplateSection;

final class PackageInitializationService
{
    public function initialize(CloseoutPackage $package): void
    {
        $template = $package->template()->with('sections.categories', 'checklistItems')->firstOrFail();

        foreach ($template->sections as $section) {
            $stage = $package->stages()->where('stage', $section->stage)->first();

            if ($stage === null) {
                continue;
            }

            $this->seedCategorySelections($stage, $section);
        }

        $this->seedChecklistResponses($package, $template);
    }

    private function seedCategorySelections(PackageStage $stage, TemplateSection $section): void
    {
        foreach ($section->categories as $category) {
            PackageCategorySelection::create([
                'package_stage_id' => $stage->id,
                'template_picture_category_id' => $category->id,
                'is_selected' => true,
                'picture_count' => $category->default_picture_count,
            ]);
        }
    }

    private function seedChecklistResponses(CloseoutPackage $package, Template $template): void
    {
        foreach ($template->checklistItems as $item) {
            PackageChecklistResponse::create([
                'closeout_package_id' => $package->id,
                'checklist_item_id' => $item->id,
                'answer' => null,
            ]);
        }
    }
}
