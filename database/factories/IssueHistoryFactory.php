<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Projects\Enums\IssueType;
use App\Domains\Projects\Models\IssueHistory;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IssueHistory>
 */
class IssueHistoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<IssueHistory>
     */
    protected $model = IssueHistory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'reported_by' => User::factory(),
            'type' => IssueType::SITE_ACCESS,
            'description' => fake()->paragraph(),
            'verizon_guidance' => null,
            'resolved_at' => null,
        ];
    }
}
