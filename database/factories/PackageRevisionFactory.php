<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\PdfGeneration\Enums\PdfType;
use App\Domains\PdfGeneration\Models\PackageRevision;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackageRevision>
 */
class PackageRevisionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<PackageRevision>
     */
    protected $model = PackageRevision::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'closeout_package_id' => CloseoutPackage::factory(),
            'type' => PdfType::FINAL,
            'revision_major' => 1,
            'revision_minor' => 0,
            'revision_label' => 'R1.0',
            'revision_date' => now()->toDateString(),
            'total_pages' => 3,
            'storage_path' => null,
            'generated_by' => User::factory(),
            'generated_at' => now(),
            'generation_duration_ms' => null,
            'renderer_version' => null,
        ];
    }
}
