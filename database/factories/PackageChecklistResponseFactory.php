<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageChecklistResponse;
use App\Domains\Templates\Models\ChecklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackageChecklistResponse>
 */
class PackageChecklistResponseFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<PackageChecklistResponse>
     */
    protected $model = PackageChecklistResponse::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'closeout_package_id' => CloseoutPackage::factory(),
            'checklist_item_id' => ChecklistItem::factory(),
            'answer' => null,
            'explain' => null,
            'is_na' => false,
            'quantity' => null,
        ];
    }
}
