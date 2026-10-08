<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\ChangeOrders\Enums\CustomerDecision;
use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\ChangeOrders\Models\ChangeOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChangeOrderItem>
 */
class ChangeOrderItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ChangeOrderItem>
     */
    protected $model = ChangeOrderItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'change_order_id' => ChangeOrder::factory(),
            'sort_order' => 0,
            'description' => fake()->sentence(6),
            'vendor_name' => fake()->optional()->company(),
            'category' => fake()->optional()->randomElement(['Third-party service', 'Labor', 'Materials', 'Equipment', 'Other']),
            'amount' => fake()->randomFloat(2, 50, 2000),
            'is_included' => true,
            'cost_absorbed_by_solucomp' => false,
            'customer_decision' => null,
            'customer_decision_note' => null,
            'decided_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'customer_decision' => CustomerDecision::APPROVED,
            'decided_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'customer_decision' => CustomerDecision::REJECTED,
            'decided_at' => now(),
        ]);
    }

    public function excluded(): static
    {
        return $this->state(fn (): array => [
            'is_included' => false,
        ]);
    }

    public function costAbsorbed(): static
    {
        return $this->state(fn (): array => [
            'cost_absorbed_by_solucomp' => true,
        ]);
    }
}
