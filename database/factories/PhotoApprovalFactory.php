<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Approval\Enums\ApprovalDecision;
use App\Domains\Approval\Models\PhotoApproval;
use App\Domains\Photos\Models\Photo;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PhotoApproval>
 */
class PhotoApprovalFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<PhotoApproval>
     */
    protected $model = PhotoApproval::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'photo_id' => Photo::factory(),
            'reviewed_by' => User::factory(),
            'decision' => ApprovalDecision::APPROVED,
            'reason' => null,
        ];
    }
}
