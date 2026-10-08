<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageSiteInfo;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackageSiteInfo>
 */
class PackageSiteInfoFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<PackageSiteInfo>
     */
    protected $model = PackageSiteInfo::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'closeout_package_id' => CloseoutPackage::factory(),
            'author_user_id' => User::factory(),
            'pm_user_id' => User::factory(),
            'site_name' => fake()->company().' Site',
            'ps_number' => fake()->numerify('PS######'),
            'project_number' => fake()->numerify('#####'),
            'address' => fake()->streetAddress(),
            'complete_date' => null,
            'vzw_engineer_name' => fake()->name(),
            'vzw_engineer_email' => fake()->safeEmail(),
            'author_name' => fake()->name(),
            'pm_name' => fake()->name(),
        ];
    }
}
