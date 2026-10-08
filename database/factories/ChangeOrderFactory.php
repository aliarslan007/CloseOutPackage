<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\ChangeOrders\Enums\ChangeOrderStatus;
use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChangeOrder>
 */
class ChangeOrderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ChangeOrder>
     */
    protected $model = ChangeOrder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'created_by' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'change_order_date' => now()->toDateString(),
            'status' => ChangeOrderStatus::DRAFT,
            'sent_at' => null,
            'decision_deadline_at' => null,
            'total_amount' => 0,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => ChangeOrderStatus::SENT_TO_CUSTOMER,
            'sent_at' => now(),
            'decision_deadline_at' => now()->addDays(30),
        ]);
    }
}
