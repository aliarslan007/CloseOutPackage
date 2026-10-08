<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Templates\Models\FavoriteTemplate;
use App\Domains\Templates\Models\Template;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FavoriteTemplate>
 */
class FavoriteTemplateFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<FavoriteTemplate>
     */
    protected $model = FavoriteTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'template_id' => Template::factory(),
            'name' => fake()->unique()->company().' Favorites',
            'snapshot' => [],
        ];
    }
}
