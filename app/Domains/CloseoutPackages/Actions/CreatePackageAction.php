<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Actions;

use App\Domains\CloseoutPackages\DTOs\CreatePackageDTO;
use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Enums\StageStatus;
use App\Domains\CloseoutPackages\Events\PackageCreated;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use App\Domains\CloseoutPackages\Models\PackageSiteInfo;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\CloseoutPackages\Services\PackageInitializationService;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Actions\LoadFavoriteTemplateAction;
use App\Domains\Templates\Enums\StageType;
use App\Domains\Templates\Models\FavoriteTemplate;
use App\Domains\Templates\Models\Template;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;

final class CreatePackageAction
{
    public function execute(CreatePackageDTO $dto, User $actor): CloseoutPackage
    {
        $project = Project::findOrFail($dto->projectId);
        // Verify the template exists (404 instead of a silent FK failure later).
        Template::findOrFail($dto->templateId);

        return DB::transaction(function () use ($dto, $actor, $project): CloseoutPackage {
            $revision = $this->determineNextRevision($dto->projectId);

            $package = CloseoutPackage::create([
                'project_id' => $dto->projectId,
                'template_id' => $dto->templateId,
                'created_by' => $actor->id,
                'status' => PackageStatus::DRAFT,
                'revision_major' => $revision['major'],
                'revision_minor' => $revision['minor'],
                'revision_date' => today(),
                'current_stage' => StageType::PRE_CONSTRUCTION,
            ]);

            $this->createStages($package);
            $this->createSiteInfo($package, $project, $actor);
            app(PackageInitializationService::class)->initialize($package);
            $this->applyFavorite($package, $dto->favoriteId);

            PackageCreated::dispatch($package);

            return $package;
        });
    }

    /**
     * @return array{major: int, minor: int}
     */
    private function determineNextRevision(int $projectId): array
    {
        /** @var CloseoutPackage|null $latestPackage */
        $latestPackage = CloseoutPackage::query()
            ->where('project_id', $projectId)
            ->orderByDesc('id')
            ->first(['revision_major', 'revision_minor']);

        if ($latestPackage === null) {
            return ['major' => 1, 'minor' => 0];
        }

        /** @var int $threshold */
        $threshold = (int) config('cop.revision_minor_threshold', 10);
        $minor = $latestPackage->revision_minor + 1;
        $major = $latestPackage->revision_major;

        if ($minor >= $threshold) {
            $major++;
            $minor = 0;
        }

        return ['major' => $major, 'minor' => $minor];
    }

    private function createStages(CloseoutPackage $package): void
    {
        foreach (StageType::cases() as $stage) {
            PackageStage::create([
                'closeout_package_id' => $package->id,
                'stage' => $stage,
                'status' => StageStatus::NOT_STARTED,
            ]);
        }
    }

    private function createSiteInfo(CloseoutPackage $package, Project $project, User $actor): void
    {
        $manager = $project->manager;

        $completeAddress = trim(implode(', ', array_filter([
            $project->address,
            $project->city,
            trim(($project->state ?? '').' '.($project->zip ?? '')),
        ])));

        PackageSiteInfo::create([
            'closeout_package_id' => $package->id,
            'author_user_id' => $actor->id,
            'pm_user_id' => $manager?->id,
            'site_name' => $project->name,
            'ps_number' => $project->ps_number,
            'project_number' => $project->project_number,
            'address' => $completeAddress ?: $project->address,
            'complete_date' => $project->completed_date,
            'vzw_engineer_name' => $project->vzw_engineer_name,
            'vzw_engineer_phone' => $project->vzw_engineer_phone,
            'vzw_engineer_email' => $project->vzw_engineer_email,
            'author_company' => $actor->company,
            'author_name' => $actor->name,
            'author_email' => $actor->email,
            'author_phone' => $actor->phone,
            'pm_name' => $manager?->name,
            'pm_phone' => $manager?->phone,
        ]);
    }

    private function applyFavorite(CloseoutPackage $package, ?int $favoriteId): void
    {
        if ($favoriteId === null) {
            return;
        }

        $favorite = FavoriteTemplate::query()->find($favoriteId);

        if ($favorite === null) {
            return;
        }

        $items = app(LoadFavoriteTemplateAction::class)->execute($favorite);

        foreach ($items as $item) {
            PackageCategorySelection::query()
                ->whereIn('package_stage_id', $package->stages()->pluck('id'))
                ->where('template_picture_category_id', $item['category_id'])
                ->update($this->selectionValues($item));
        }
    }

    /**
     * Sanitizes a favorite snapshot item before persisting it, mirroring the
     * bounds enforced by SaveDraftRequest so crafted data cannot overflow the
     * unsignedTinyInteger picture_count column.
     *
     * @param  array<string, mixed>  $item
     * @return array{is_selected: bool, picture_count: int, user_heading: ?string, user_notes: ?string}
     */
    private function selectionValues(array $item): array
    {
        return [
            'is_selected' => (bool) ($item['is_selected'] ?? true),
            'picture_count' => min(max($this->intValue($item['picture_count'] ?? null, 1), 1), 99),
            'user_heading' => is_string($item['user_heading'] ?? null) ? $item['user_heading'] : null,
            'user_notes' => is_string($item['user_notes'] ?? null) ? $item['user_notes'] : null,
        ];
    }

    private function intValue(mixed $value, int $fallback): int
    {
        return is_int($value) ? $value : $fallback;
    }
}
