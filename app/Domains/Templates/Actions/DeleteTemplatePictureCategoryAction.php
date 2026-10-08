<?php

declare(strict_types=1);

namespace App\Domains\Templates\Actions;

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use App\Domains\Templates\Models\TemplatePictureCategory;
use Illuminate\Support\Facades\DB;

final class DeleteTemplatePictureCategoryAction
{
    public function execute(TemplatePictureCategory $category): void
    {
        DB::transaction(function () use ($category): void {
            if ($category->category_preset_id !== null) {
                $category->update(['category_preset_id' => null]);
            }

            PackageCategorySelection::query()
                ->where('template_picture_category_id', $category->id)
                ->whereDoesntHave('photos')
                ->whereHas('stage.package', fn ($query) => $query->whereNotIn('status', [
                    PackageStatus::APPROVED,
                    PackageStatus::CUSTOMER_REVIEW,
                ]))
                ->delete();

            $category->delete();
        });
    }
}
