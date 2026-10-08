<?php

declare(strict_types=1);

namespace App\Domains\Templates\Actions;

use App\Domains\Templates\Models\CategoryPreset;
use App\Domains\Templates\Models\TemplatePictureCategory;
use Illuminate\Support\Facades\DB;

/**
 * Copies selected library presets into one template stage as regular picture
 * categories. Copy-on-pull: later preset edits/deletes never mutate templates.
 */
final class PullCategoryPresetsAction
{
    /**
     * @param  list<int>  $presetIds
     * @return array{added: int, skipped: int}
     */
    public function execute(int $sectionId, array $presetIds): array
    {
        return DB::transaction(function () use ($sectionId, $presetIds): array {
            $presets = CategoryPreset::query()
                ->whereIn('id', $presetIds)
                ->active()
                ->orderBy('name')
                ->get();

            $alreadyUsed = TemplatePictureCategory::query()
                ->where('template_section_id', $sectionId)
                ->whereNotNull('category_preset_id')
                ->pluck('category_preset_id');

            $nextSortOrder = (int) TemplatePictureCategory::query()
                ->where('template_section_id', $sectionId)
                ->max('sort_order') + 1;

            $added = 0;
            $skipped = 0;

            foreach ($presets as $preset) {
                if ($alreadyUsed->contains($preset->id)) {
                    $skipped++;

                    continue;
                }

                TemplatePictureCategory::create([
                    'template_section_id' => $sectionId,
                    'category_preset_id' => $preset->id,
                    'name' => $preset->name,
                    'description' => $preset->description,
                    'default_picture_count' => $preset->default_picture_count,
                    'is_required' => $preset->is_required,
                    'sort_order' => $nextSortOrder++,
                ]);

                $added++;
            }

            return ['added' => $added, 'skipped' => $skipped];
        });
    }
}
