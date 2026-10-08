<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Projects\Models\IssueComment;
use App\Domains\Projects\Models\IssueHistory;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IssueComment>
 */
class IssueCommentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<IssueComment>
     */
    protected $model = IssueComment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'issue_history_id' => IssueHistory::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
