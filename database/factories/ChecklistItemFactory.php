<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Templates\Models\ChecklistItem;
use App\Domains\Templates\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistItem>
 */
class ChecklistItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ChecklistItem>
     */
    protected $model = ChecklistItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'template_id' => Template::factory(),
            'label' => fake()->sentence(),
            'has_explain_field' => false,
            'allows_na' => false,
            'sort_order' => 0,
        ];
    }
}
