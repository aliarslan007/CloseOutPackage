<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Crew\Models\CrewAssignment;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CrewAssignment>
 */
class CrewAssignmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<CrewAssignment>
     */
    protected $model = CrewAssignment::class;

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
            'role_description' => null,
            'stage' => null,
        ];
    }
}
