<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\Templates\Models\TemplatePictureCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackageCategorySelection>
 */
class PackageCategorySelectionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<PackageCategorySelection>
     */
    protected $model = PackageCategorySelection::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'package_stage_id' => PackageStage::factory(),
            'template_picture_category_id' => TemplatePictureCategory::factory(),
            'is_selected' => true,
            'picture_count' => 1,
            'user_heading' => null,
            'user_notes' => null,
        ];
    }
}
