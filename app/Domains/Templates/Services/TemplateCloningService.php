<?php

declare(strict_types=1);

namespace App\Domains\Templates\Services;

use App\Domains\Templates\Actions\CreateChecklistItemAction;
use App\Domains\Templates\Actions\CreateTemplatePictureCategoryAction;
use App\Domains\Templates\Actions\CreateTemplateSectionAction;
use App\Domains\Templates\DTOs\CreateChecklistItemDTO;
use App\Domains\Templates\DTOs\CreateTemplatePictureCategoryDTO;
use App\Domains\Templates\DTOs\CreateTemplateSectionDTO;
use App\Domains\Templates\Enums\StageType;
use App\Domains\Templates\Models\Template;
use Illuminate\Support\Facades\DB;

final class TemplateCloningService
{
    public function duplicate(Template $original, ?int $marketId = null, ?string $name = null): Template
    {
        $copy = DB::transaction(function () use ($original, $marketId, $name): Template {
            $copy = Template::create([
                'market_id' => $marketId ?? $original->market_id,
                'name' => $name ?? $this->uniqueCopyName($original),
                'description' => $original->description,
                'additional_site_notes' => $original->additional_site_notes,
                'is_active' => $original->is_active,
                'created_by' => $original->created_by,
                'duplicated_from_id' => $original->id,
            ]);

            $this->cloneSections($original, $copy);
            $this->cloneChecklist($original, $copy);

            return $copy;
        });

        // File I/O stays outside the transaction: a rollback cannot undo the
        // disk writes a media copy performs.
        $this->cloneAdditionalNoteImages($original, $copy);

        return $copy;
    }

    private function cloneAdditionalNoteImages(Template $original, Template $copy): void
    {
        foreach ($original->getMedia(Template::ADDITIONAL_NOTES_COLLECTION) as $media) {
            $media->copy($copy, Template::ADDITIONAL_NOTES_COLLECTION);
        }
    }

    private function cloneSections(Template $original, Template $copy): void
    {
        foreach ($original->sections()->with('categories')->get() as $section) {
            /** @var StageType $stage */
            $stage = $section->stage;

            $copySection = app()->make(CreateTemplateSectionAction::class)
                ->execute(new CreateTemplateSectionDTO(
                    templateId: $copy->id,
                    stage: $stage,
                    heading: $section->heading,
                    adminNotes: $section->admin_notes,
                    sortOrder: $section->sort_order,
                ));

            foreach ($section->categories as $category) {
                app()->make(CreateTemplatePictureCategoryAction::class)
                    ->execute(new CreateTemplatePictureCategoryDTO(
                        templateSectionId: $copySection->id,
                        name: $category->name,
                        description: $category->description,
                        defaultPictureCount: $category->default_picture_count,
                        sortOrder: $category->sort_order,
                        isRequired: $category->is_required,
                    ));
            }
        }
    }

    private function cloneChecklist(Template $original, Template $copy): void
    {
        foreach ($original->checklistItems()->get() as $item) {
            app()->make(CreateChecklistItemAction::class)
                ->execute(new CreateChecklistItemDTO(
                    templateId: $copy->id,
                    label: $item->label,
                    hasExplainField: $item->has_explain_field,
                    sortOrder: $item->sort_order,
                    allowsNa: (bool) $item->allows_na,
                ));
        }
    }

    private function uniqueCopyName(Template $original): string
    {
        $base = $original->name.' Copy';
        $name = $base;
        $suffix = 2;

        while (Template::withTrashed()->where('market_id', $original->market_id)->where('name', $name)->exists()) {
            $name = $base.' '.$suffix;
            $suffix++;
        }

        return $name;
    }
}
