<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Enums\StageType;
use App\Domains\Templates\Models\Template;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CloseoutPackage>
 */
class CloseoutPackageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<CloseoutPackage>
     */
    protected $model = CloseoutPackage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'template_id' => fn (array $attributes): int => Template::factory()->create([
                'market_id' => $this->marketIdForProject($attributes['project_id']),
            ])->id,
            'created_by' => User::factory(),
            'status' => PackageStatus::DRAFT,
            'revision_major' => 1,
            'revision_minor' => 0,
            'current_stage' => StageType::PRE_CONSTRUCTION,
            'notes' => null,
        ];
    }

    private function marketIdForProject(mixed $projectId): int
    {
        $project = is_int($projectId) ? Project::find($projectId) : null;

        return $project !== null ? $project->market_id : Market::factory()->create()->id;
    }
}
