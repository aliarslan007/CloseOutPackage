<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Markets\Models\Market;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Market>
 */
class MarketFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Market>
     */
    protected $model = Market::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->city().' Market';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}
