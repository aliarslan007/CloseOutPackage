<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\CloseoutPackages\Enums\StageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\Templates\Enums\StageType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackageStage>
 */
class PackageStageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<PackageStage>
     */
    protected $model = PackageStage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'closeout_package_id' => CloseoutPackage::factory(),
            'stage' => StageType::PRE_CONSTRUCTION,
            'status' => StageStatus::NOT_STARTED,
            'submitted_at' => null,
            'submitted_by' => null,
            'draft_saved_at' => null,
        ];
    }
}
