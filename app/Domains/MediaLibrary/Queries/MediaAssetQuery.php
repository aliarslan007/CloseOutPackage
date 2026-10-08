<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Queries;

use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Enums\MediaPickerScope;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\MediaLibrary\Requests\ListMediaAssetsRequest;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;

/**
 * Context-aware, role-scoped listing for the library grid and the COP picker.
 *
 * Admins see everything (optionally filtered by user). Managers see their own
 * uploads plus assets tagged to projects they manage (i.e. their assigned
 * supervisors' uploads). Field technicians see their own uploads plus assets
 * tagged to projects they are assigned to (their manager's uploads on those
 * projects). Admin-uploaded assets are globally visible to staff. Picker calls
 * additionally pass the project context and a scope (this project / this
 * market / everything visible).
 */
final class MediaAssetQuery
{
    /**
     * @return CursorPaginator<int, MediaAsset>
     */
    public function paginate(
        ListMediaAssetsRequest $request,
        User $viewer,
        ?int $contextProjectId = null,
    ): CursorPaginator {
        $query = MediaAsset::query()
            ->with([
                'market',
                'project',
                'media', // source_image_url / hasMedia appends — avoid per-row queries
                'owner' => fn ($q) => $q->with(['roles', 'media']),
                'sourceAsset',
            ])
            ->whereNull('deleted_at')
            // Pointers hold no bytes — listing them would show the same image twice.
            ->whereNull('duplicate_of_asset_id');

        $statusFilter = $request->string('status')->toString();

        if ($contextProjectId !== null) {
            $query->where('status', MediaAssetStatus::READY->value);
        } elseif ($statusFilter === 'all') {
            // Show all statuses (ready, processing, failed)
        } elseif ($statusFilter === MediaAssetStatus::PROCESSING->value) {
            $query->where('status', MediaAssetStatus::PROCESSING->value);
        } elseif ($statusFilter === MediaAssetStatus::FAILED->value) {
            $query->where('status', MediaAssetStatus::FAILED->value);
        } else {
            // Default: ready assets only
            $query->where('status', MediaAssetStatus::READY->value);
        }

        // Role-scoped visibility — managers also see their managed projects.
        $this->applyVisibilityScope($query, $request, $viewer);

        $this->applyPickerScope($query, $request, $contextProjectId);

        if ($request->filled('search')) {
            $query->where('original_filename', 'like', '%'.$request->string('search').'%');
        }

        if ($contextProjectId === null) {
            $this->applyProjectFilter($query, $request);
        }

        if ($request->filled('market_id')) {
            $query->where('market_id', $request->integer('market_id'));
        }

        if ($request->filled('from')) {
            $query->where('captured_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->where('captured_at', '<=', $request->date('to'));
        }

        [$column, $direction] = $this->resolveSort($request->string('sort', '-created_at')->toString());

        return $query
            ->orderBy($column, $direction)
            ->orderByDesc('id') // Stable tie-breaker for cursor pagination.
            ->cursorPaginate(perPage: 24);
    }

    /**
     * Per-status counts for the stats bar, using the same visibility rule as
     * the grid (admins may scope to one user via `user_id`).
     *
     * @return array{processing: int, ready: int, failed: int, total: int}
     */
    public function statusCounts(ListMediaAssetsRequest $request, User $viewer): array
    {
        // Same exclusion as paginate() so the stats bar matches the grid.
        $query = MediaAsset::query()
            ->whereNull('deleted_at')
            ->whereNull('duplicate_of_asset_id');

        $this->applyVisibilityScope($query, $request, $viewer);

        $this->applyProjectFilter($query, $request);

        /** @var array<string, int> $rows */
        $rows = (clone $query)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $counts = [
            'processing' => $rows[MediaAssetStatus::PROCESSING->value] ?? 0,
            'ready' => $rows[MediaAssetStatus::READY->value] ?? 0,
            'failed' => $rows[MediaAssetStatus::FAILED->value] ?? 0,
        ];

        $counts['total'] = array_sum($counts);

        return $counts;
    }

    /**
     * Uploaders visible in the uploader filter dropdown, grouped per section:
     * - Admins see all field technicians (and managers).
     * - Managers see field technicians assigned to their managed projects.
     * - Field technicians see managers and admins of their assigned projects.
     *
     * @return Collection<int, User>
     */
    public function assignedTechnicians(User $viewer): Collection
    {
        $query = match (true) {
            $viewer->hasRole(Role::ADMIN->value) => User::role([Role::FIELD_SUPERVISOR->value, Role::MANAGER->value])
                ->where('id', '!=', $viewer->id),

            $viewer->hasRole(Role::MANAGER->value) => User::role(Role::FIELD_SUPERVISOR->value)
                ->whereIn('id', fn (QueryBuilder $sub) => $sub->select('user_id')->from('project_field_supervisors')->whereIn('project_id', Project::query()->select('id')->where('manager_id', $viewer->id))),

            // Managers and admins behind the technician's assigned projects.
            $viewer->hasRole(Role::FIELD_SUPERVISOR->value) => User::role([Role::MANAGER->value, Role::ADMIN->value])
                ->where(function (Builder $query) use ($viewer): void {
                    $query->whereIn('id', Project::query()
                        ->select('manager_id')
                        ->whereHas('fieldSupervisors', fn (Builder $q) => $q->whereKey($viewer->id)))
                        ->orWhereHas('roles', fn (Builder $q) => $q->where('name', Role::ADMIN->value));
                }),

            // Anyone else (e.g. a customer) has no team.
            default => null,
        };

        return ($query ?? User::query()->whereRaw('1 = 0'))
            ->withTrashed()
            ->with(['roles', 'media'])
            ->orderBy('name')
            ->get()
            ->values();
    }

    /**
     * Team uploaders with visible-asset counts for toolbar filtering.
     *
     * @return Collection<int, User>
     */
    public function teamUploaders(User $viewer): Collection
    {
        $supervisors = $this->assignedTechnicians($viewer);

        if ($supervisors->isEmpty()) {
            return $supervisors;
        }

        // Same visibility rule as the grid so folder counts never drift.
        /** @var array<int, int> $counts */
        $counts = MediaAsset::query()
            ->selectRaw('user_id, COUNT(*) as total')
            ->whereIn('user_id', $supervisors->modelKeys())
            ->whereNull('deleted_at')
            ->tap(fn (Builder $query) => $this->applyRoleVisibility($query, $viewer))
            ->groupBy('user_id')
            ->pluck('total', 'user_id')
            ->all();

        return $supervisors
            ->each(fn (User $supervisor) => $supervisor->setAttribute('assets_count', (int) ($counts[(int) $supervisor->id] ?? 0)))
            ->values();
    }

    /**
     * Role-scoped visibility plus the optional uploader narrow-down.
     *
     * @param  Builder<MediaAsset>  $query
     */
    private function applyVisibilityScope(Builder $query, ListMediaAssetsRequest $request, User $viewer): void
    {
        $this->applyRoleVisibility($query, $viewer);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }
    }

    /**
     * Own uploads for everyone; managers additionally see all assets on
     * projects they manage plus unassigned assets uploaded by their assigned
     * supervisors. Field technicians see assets on projects they are assigned
     * to plus admin uploads (admin uploads are globally visible to staff —
     * matches MediaAssetPolicy::isUploaderAdmin). Grouped so later
     * project/market filters narrow properly.
     *
     * @param  Builder<MediaAsset>  $query
     */
    private function applyRoleVisibility(Builder $query, User $viewer): void
    {
        if ($viewer->hasRole(Role::ADMIN->value)) {
            return; // Admins see everything.
        }

        if ($viewer->hasRole(Role::FIELD_SUPERVISOR->value)) {
            $assignedProjectIds = DB::table('project_field_supervisors')
                ->where('user_id', $viewer->id)
                ->select('project_id');

            $query->where(function (Builder $q) use ($viewer, $assignedProjectIds): void {
                $q->where('user_id', $viewer->id)
                    ->orWhereIn('project_id', $assignedProjectIds)
                    // Managers/admins of the technician's assigned projects:
                    // unassigned uploads are visible too (generic photos).
                    ->orWhere(function (Builder $sub) use ($assignedProjectIds): void {
                        $sub->whereNull('project_id')
                            ->whereIn('user_id', function (QueryBuilder $teamUserIds) use ($assignedProjectIds): void {
                                $teamUserIds->select('manager_id')->from('projects')->whereIn('id', $assignedProjectIds)
                                    ->unionAll(
                                        User::role(Role::ADMIN->value)->select('id')->toBase()
                                    );
                            });
                    })
                    // Admin-uploaded assets are globally visible to staff.
                    ->orWhereHas('owner', fn (Builder $sub) => $sub->role(Role::ADMIN->value));
            });

            return;
        }

        if (! $viewer->hasRole(Role::MANAGER->value)) {
            $query->where('user_id', $viewer->id);

            return;
        }

        $managedProjects = Project::query()->select('id')->where('manager_id', $viewer->id);
        $assignedSupervisorIds = DB::table('project_field_supervisors')
            ->whereIn('project_id', $managedProjects)
            ->select('user_id');

        $query->where(function (Builder $q) use ($viewer, $managedProjects, $assignedSupervisorIds): void {
            $q->where('user_id', $viewer->id)
                ->orWhereIn('project_id', $managedProjects)
                ->orWhere(function (Builder $sub) use ($assignedSupervisorIds): void {
                    $sub->whereNull('project_id')
                        ->whereIn('user_id', $assignedSupervisorIds);
                })
                // Admin-uploaded assets are globally visible to managers.
                ->orWhereHas('owner', fn (Builder $sub) => $sub->role(Role::ADMIN->value));
        });
    }

    /**
     * Library-grid project filter: one tagged project, or uploads not tagged
     * to any project. Unassigned wins when both arrive; the picker never
     * reaches here since it always carries a project context.
     *
     * @param  Builder<MediaAsset>  $query
     */
    private function applyProjectFilter(Builder $query, ListMediaAssetsRequest $request): void
    {
        if ($request->boolean('unassigned')) {
            $query->whereNull('project_id');
        } elseif ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }
    }

    /**
     * @param  Builder<MediaAsset>  $query
     */
    private function applyPickerScope(
        Builder $query,
        ListMediaAssetsRequest $request,
        ?int $contextProjectId,
    ): void {
        if ($contextProjectId === null) {
            return; // Library index — no picker context.
        }

        // Generic photos: uploads tagged to no project at all. Wins over the
        // scope, mirroring the library-grid filter in applyProjectFilter().
        if ($request->boolean('unassigned')) {
            $query->whereNull('project_id');

            return;
        }

        $scope = $request->enum('scope', MediaPickerScope::class)
            ?? MediaPickerScope::PROJECT;

        if ($scope === MediaPickerScope::PROJECT) {
            $query->where('project_id', $contextProjectId);
        } elseif ($scope === MediaPickerScope::MARKET) {
            $marketId = Project::query()->whereKey($contextProjectId)->value('market_id');

            if ($marketId !== null) {
                $query->whereHas('project', fn ($q) => $q->where('market_id', $marketId));
            }
        }
        // MediaPickerScope::ALL: no extra scope.
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveSort(string $sort): array
    {
        $column = 'created_at';
        $direction = 'asc';

        if (str_starts_with($sort, '-')) {
            $direction = 'desc';
            $sort = substr($sort, 1);
        }

        if (in_array($sort, ['created_at', 'captured_at', 'original_filename', 'file_size_bytes'], true)) {
            $column = $sort;
        }

        return [$column, $direction];
    }
}
