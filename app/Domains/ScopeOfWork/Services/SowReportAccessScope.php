<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Services;

use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Resolves the markets and projects a user may see when listing, creating or
 * filtering SOW reports. Extracted from SowReportController so the controller
 * stays a thin HTTP layer.
 */
final class SowReportAccessScope
{
    /**
     * @return list<int>
     */
    public function marketIdsFor(User $user): array
    {
        if ($user->hasRole(Role::ADMIN->value)) {
            /** @var list<int> */
            return Market::query()->active()->pluck('id')->all();
        }

        /** @var list<int> $marketIds */
        $marketIds = $user->markets()->pluck('markets.id')->all();

        /** @var list<int> $projectMarkets */
        $projectMarkets = match (true) {
            $user->hasRole(Role::MANAGER->value) => Project::query()
                ->where('manager_id', $user->id)
                ->whereNotNull('market_id')
                ->pluck('market_id')
                ->all(),
            $user->hasRole(Role::FIELD_SUPERVISOR->value) => Project::query()
                ->whereHas('fieldSupervisors', fn (Builder $q): Builder => $q->whereKey($user->id))
                ->whereNotNull('market_id')
                ->pluck('market_id')
                ->all(),
            default => [],
        };

        /** @var list<int> $merged */
        $merged = array_values(array_unique(array_merge($marketIds, $projectMarkets)));

        // Non-customer staff without explicit assignments fall back to all active markets.
        if ($merged === [] && ! $user->hasRole(Role::CUSTOMER->value)) {
            /** @var list<int> */
            return Market::query()->active()->pluck('id')->all();
        }

        return $merged;
    }

    /**
     * @param  list<int>  $marketIds
     * @return Collection<int, Project>
     */
    public function projectsFor(User $user, array $marketIds): Collection
    {
        $columns = ['id', 'name', 'market_id', 'ps_number', 'address', 'city', 'county', 'state', 'latitude', 'longitude', 'description'];

        if ($user->hasRole(Role::ADMIN->value)) {
            $query = Project::query()->whereIn('market_id', $marketIds);
        } elseif ($user->hasRole(Role::MANAGER->value)) {
            $query = Project::query()
                ->whereIn('market_id', $marketIds)
                ->where(function (Builder $q) use ($user): void {
                    $q->where('manager_id', $user->id)
                        ->orWhereHas('fieldSupervisors', fn (Builder $fs): Builder => $fs->whereKey($user->id));
                });
        } elseif ($user->hasRole(Role::FIELD_SUPERVISOR->value)) {
            $query = Project::query()
                ->whereIn('market_id', $marketIds)
                ->whereHas('fieldSupervisors', fn (Builder $q): Builder => $q->whereKey($user->id));
        } else {
            return Project::newCollection();
        }

        return $this->withSitePhotoUrls(
            $query->with('media')->orderBy('name')->get($columns)
        );
    }

    /**
     * Preview URL for each project's site photo, so SOW create can show (and
     * auto-attach) it without a second round-trip. Null when the project has
     * no site photo.
     *
     * @param  Collection<int, Project>  $projects
     * @return Collection<int, Project>
     */
    private function withSitePhotoUrls(Collection $projects): Collection
    {
        return $projects->each(function (Project $project): void {
            $project->setAttribute(
                'site_photo_url',
                $this->sitePhotoUrl($project)
            );
        });
    }

    private function sitePhotoUrl(Project $project): ?string
    {
        $media = $project->getFirstMedia(Project::SITE_PHOTO_COLLECTION);

        if ($media === null) {
            return null;
        }

        // Same cache-busting pattern as the project detail page.
        return route('projects.site-photo.show', $project).'?v='.$media->id;
    }
}
