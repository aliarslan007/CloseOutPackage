<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Templates\Enums\StageType;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplateSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TemplateSection>
 */
class TemplateSectionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<TemplateSection>
     */
    protected $model = TemplateSection::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'template_id' => Template::factory()->withoutSections(),
            'stage' => StageType::PRE_CONSTRUCTION,
            'heading' => 'Pre Construction',
            'admin_notes' => null,
            'sort_order' => 1,
        ];
    }
}
