<?php

declare(strict_types=1);

namespace App\Domains\Dashboard\Services;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Support\PackageLockFlags;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Models\Template;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class LiveProjectVisibilityService
{
    private const CACHE_TTL_SECONDS = 30;

    /**
     * @return array<string, mixed>
     */
    public function snapshot(User $user): array
    {
        try {
            if (! Cache::supportsTags()) {
                return $this->build($user);
            }

            return Cache::tags(['visibility'])
                ->remember("visibility.{$user->id}", self::CACHE_TTL_SECONDS, fn (): array => $this->build($user));
        } catch (Throwable $exception) {
            Log::warning('Visibility cache unavailable, falling back to a direct query', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return $this->build($user);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function build(User $user): array
    {
        /** @var list<int> $marketIds */
        $marketIds = $user->markets()->pluck('markets.id')->all();

        $projectsQuery = match (true) {
            $user->hasRole(Role::ADMIN->value) => Project::query(),
            $user->hasRole(Role::MANAGER->value) => Project::query()->where('manager_id', $user->id),
            default => Project::query()->whereHas(
                'fieldSupervisors',
                fn (Builder $query): Builder => $query->whereKey($user->id)
            ),
        };

        $projects = $projectsQuery
            ->with(['market', 'latestPackage'])
            ->get(['id', 'name', 'market_id', 'status', 'progress_percentage']);

        $templateCount = Template::query()->whereIn('market_id', $marketIds)->count();

        $projectIds = $projects->pluck('id');

        $photoCount = Photo::query()
            ->whereIn('closeout_package_id', CloseoutPackage::query()->whereIn('project_id', $projectIds)->select('id'))
            ->count();

        return [
            'projects' => $projects,
            'templateCount' => $templateCount,
            'photoCount' => $photoCount,
            'generatedAt' => now()->toIso8601String(),
        ];
    }

    /**
     * Read-only project detail for the Live Visibility drill-down page.
     */
    public function projectDetail(Project $project, User $user, int $issuesPage = 1): Project
    {
        $project->load([
            'market',
            'manager',
            'fieldSupervisors',
            'closeoutPackages' => fn (HasMany $query): HasMany => $query
                ->with([
                    'template',
                    'project:id,manager_id',
                    'lockedBy:id,name',
                    'stages' => fn (Relation $stagesQuery) => $stagesQuery
                        ->orderBy('id')
                        ->with([
                            'photos' => fn (Relation $photosQuery) => $photosQuery
                                ->select([
                                    'id',
                                    'closeout_package_id',
                                    'package_stage_id',
                                    'package_category_selection_id',
                                    'position',
                                    'status',
                                    'flag_reason',
                                    'captured_at',
                                    'replaced_at',
                                    'gps_latitude',
                                    'gps_longitude',
                                ])
                                ->whereNull('replaced_at')
                                ->orderBy('position')
                                ->with([
                                    'categorySelection' => fn (Relation $selectionQuery) => $selectionQuery
                                        ->select(['id', 'package_stage_id', 'template_picture_category_id'])
                                        ->with('templatePictureCategory:id,name'),
                                    'photoApproval' => fn (Relation $approvalQuery) => $approvalQuery
                                        ->select(['id', 'photo_id', 'reason']),
                                ]),
                        ]),
                    'checklistResponses.checklistItem',
                ])
                ->withCount([
                    'photos' => fn (Builder $photoCountQuery): Builder => $photoCountQuery->whereNull('replaced_at'),
                    'checklistResponses',
                    'checklistResponses as answered_checklist_responses' => fn (Builder $countQuery): Builder => $countQuery->where(
                        fn (Builder $q): Builder => $q->whereNotNull('answer')->orWhere('is_na', true)
                    ),
                ])
                ->latest(),
        ]);

        // Latest four issues by default; the page component asks for earlier/later
        // pages through ?issues_page=n. Keeping this server-drawn is what keeps the
        // page from shipping every issue for a busy project.
        $project->issues = $project->issues()->latest()->paginate(3, ['*'], 'issues_page', $issuesPage);

        PackageLockFlags::attach($project->closeoutPackages, $user);

        return $project;
    }

    public function sitePhotoUrl(Project $project): ?string
    {
        if (! $project->relationLoaded('media')) {
            $project->loadMissing('media');
        }

        $media = $project->getFirstMedia(Project::SITE_PHOTO_COLLECTION);

        // Cache-bust with the media id so a replaced photo gets a fresh URL.
        return $media !== null ? route('projects.site-photo.show', $project).'?v='.$media->id : null;
    }
}
