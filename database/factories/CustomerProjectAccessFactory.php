<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerProjectAccess>
 */
class CustomerProjectAccessFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<CustomerProjectAccess>
     */
    protected $model = CustomerProjectAccess::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'project_id' => Project::factory(),
            'granted_by' => null,
            'expires_at' => null,
        ];
    }
}
