<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Customers\Models\Customer;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Customer>
     */
    protected $model = Customer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'code' => strtoupper(Str::random(8)),
            'description' => fake()->sentence(),
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}
