<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Templates\Models\CategoryPreset;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoryPreset>
 */
class CategoryPresetFactory extends Factory
{
    /**
     * @var class-string<CategoryPreset>
     */
    protected $model = CategoryPreset::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true).' Photo Group',
            'description' => fake()->sentence(),
            'default_picture_count' => fake()->numberBetween(1, 4),
            'is_required' => true,
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }

    /**
     * @return Factory<CategoryPreset>
     */
    public function inactive(): Factory
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    /**
     * @return Factory<CategoryPreset>
     */
    public function optional(): Factory
    {
        return $this->state(fn (): array => ['is_required' => false]);
    }
}
