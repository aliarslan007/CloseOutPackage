<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\Photos\Enums\PhotoStatus;
use App\Domains\Photos\Models\Photo;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Photo>
 */
class PhotoFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Photo>
     */
    protected $model = Photo::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'closeout_package_id' => CloseoutPackage::factory(),
            'package_stage_id' => PackageStage::factory(),
            'package_category_selection_id' => PackageCategorySelection::factory(),
            'uploaded_by' => User::factory(),
            'position' => 1,
            'status' => PhotoStatus::PENDING,
            'gps_latitude' => null,
            'gps_longitude' => null,
            'gps_accuracy' => null,
            'captured_at' => null,
            'is_approved' => null,
        ];
    }
}
