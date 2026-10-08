<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Enums\ProjectStatus;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Project>
     */
    protected $model = Project::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'market_id' => Market::factory(),
            'name' => fake()->unique()->company().' Site',
            'ps_number' => fake()->numerify('PS######'),
            'project_number' => fake()->numerify('#####'),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->randomLetter().fake()->randomLetter(),
            'zip' => fake()->postcode(),
            'vzw_engineer_name' => fake()->name(),
            'vzw_engineer_email' => fake()->safeEmail(),
            'vzw_engineer_phone' => fake()->phoneNumber(),
            'status' => ProjectStatus::PENDING,
            'progress_percentage' => 0,
            'manager_id' => User::factory(),
            'created_by' => User::factory(),
            'notes' => null,
        ];
    }
}
