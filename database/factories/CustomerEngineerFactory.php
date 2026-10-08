<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerEngineer;
use App\Domains\Markets\Models\Market;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerEngineer>
 */
class CustomerEngineerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<CustomerEngineer>
     */
    protected $model = CustomerEngineer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'market_id' => Market::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('(###) ###-####'),
            'title' => fake()->jobTitle(),
            'is_default' => false,
            'user_id' => null,
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Mark the engineer as the region's default contact.
     */
    public function default(): static
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }
}
