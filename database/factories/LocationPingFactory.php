<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Projects\Models\Project;
use App\Domains\Tracking\Enums\TrackingStatus;
use App\Domains\Tracking\Models\LocationPing;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LocationPing>
 */
class LocationPingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<LocationPing>
     */
    protected $model = LocationPing::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'accuracy' => fake()->optional()->randomFloat(2, 5, 150),
            'status' => TrackingStatus::ACTIVE,
            'pinged_at' => now(),
        ];
    }

    public function idle(): static
    {
        return $this->state(fn (): array => ['status' => TrackingStatus::IDLE]);
    }

    public function offline(): static
    {
        return $this->state(fn (): array => ['status' => TrackingStatus::OFFLINE]);
    }

    public function stale(int $minutes = 10): static
    {
        return $this->state(fn (): array => ['pinged_at' => now()->subMinutes($minutes)]);
    }
}
