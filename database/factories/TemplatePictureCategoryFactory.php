<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Templates\Models\TemplatePictureCategory;
use App\Domains\Templates\Models\TemplateSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TemplatePictureCategory>
 */
class TemplatePictureCategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<TemplatePictureCategory>
     */
    protected $model = TemplatePictureCategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'template_section_id' => TemplateSection::factory(),
            'name' => fake()->unique()->word(),
            'description' => fake()->sentence(),
            'default_picture_count' => 1,
            'is_required' => false,
            'sort_order' => 0,
        ];
    }
}
