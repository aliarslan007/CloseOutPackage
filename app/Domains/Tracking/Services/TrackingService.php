<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Services;

use App\Domains\Projects\Models\Project;
use App\Domains\Tracking\Enums\TrackingStatus;
use App\Domains\Tracking\Models\LocationPing;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Illuminate\Database\Eloquent\Collection;

/**
 * Single source for the "recent pings" query shared by the Inertia render
 * and the JSON polling endpoint (DRY).
 */
final class TrackingService
{
    /**
     * @return Collection<int, LocationPing>
     */
    public function recentPings(Project $project): Collection
    {
        $project->loadMissing([
            'fieldSupervisors:id,name,email,phone',
            'fieldSupervisors.roles:id,name',
            'fieldSupervisors.media',
        ]);

        /** @var Collection<int, LocationPing> $pings */
        $pings = LocationPing::query()
            ->with(['user:id,name,email,phone', 'user.roles:id,name', 'user.media', 'project:id,name,address'])
            ->where('project_id', $project->id)
            ->get(['id', 'project_id', 'user_id', 'latitude', 'longitude', 'accuracy', 'status', 'pinged_at'])
            ->keyBy('user_id');

        $result = new Collection;

        foreach ($project->fieldSupervisors as $supervisor) {
            $ping = $pings->get($supervisor->id);
            $result->push($ping !== null ? $this->evaluatePingStatus($ping) : $this->createOfflinePlaceholder($project, $supervisor));
        }

        foreach ($pings as $userId => $ping) {
            if (! $result->contains(fn (LocationPing $p): bool => $p->user_id === $userId)) {
                $result->push($this->evaluatePingStatus($ping));
            }
        }

        return $result;
    }

    /**
     * @return Collection<int, LocationPing>
     */
    public function recentPingsForUser(User $user, ?int $projectId = null): Collection
    {
        $projectIds = $this->accessibleProjectIds($user);

        if ($projectId !== null) {
            if (! in_array($projectId, $projectIds, true)) {
                return new Collection;
            }
            $project = Project::query()->find($projectId);

            return $project !== null ? $this->recentPings($project) : new Collection;
        }

        if ($projectIds === []) {
            return new Collection;
        }

        /** @var Collection<int, Project> $projects */
        $projects = Project::query()
            ->whereIn('id', $projectIds)
            ->with([
                'fieldSupervisors:id,name,email,phone',
                'fieldSupervisors.roles:id,name',
                'fieldSupervisors.media',
            ])
            ->get(['id', 'name', 'address']);

        /** @var Collection<int, LocationPing> $pings */
        $pings = LocationPing::query()
            ->with(['user:id,name,email,phone', 'user.roles:id,name', 'user.media', 'project:id,name,address'])
            ->whereIn('project_id', $projectIds)
            ->get(['id', 'project_id', 'user_id', 'latitude', 'longitude', 'accuracy', 'status', 'pinged_at'])
            ->keyBy(fn (LocationPing $p): string => $p->project_id.'-'.$p->user_id);

        $result = new Collection;

        foreach ($projects as $project) {
            foreach ($project->fieldSupervisors as $supervisor) {
                $key = $project->id.'-'.$supervisor->id;
                $ping = $pings->get($key);
                $result->push($ping !== null ? $this->evaluatePingStatus($ping) : $this->createOfflinePlaceholder($project, $supervisor));
            }
        }

        foreach ($pings as $ping) {
            $matched = $result->contains(
                fn (LocationPing $p): bool => $p->project_id === $ping->project_id && $p->user_id === $ping->user_id
            );
            if (! $matched) {
                $result->push($this->evaluatePingStatus($ping));
            }
        }

        return $result;
    }

    /**
     * If a ping is older than the active window and still marked active, mark it offline.
     */
    private function evaluatePingStatus(LocationPing $ping): LocationPing
    {
        if ($ping->pinged_at !== null && $ping->pinged_at->lt(now()->subMinutes(LocationPing::ACTIVE_WINDOW_MINUTES))) {
            $ping->status = TrackingStatus::OFFLINE;
        }

        return $ping;
    }

    /**
     * Create an in-memory placeholder ping for an assigned supervisor who hasn't sent GPS fixes yet.
     */
    private function createOfflinePlaceholder(Project $project, User $supervisor): LocationPing
    {
        $ping = new LocationPing([
            'project_id' => $project->id,
            'user_id' => $supervisor->id,
            'latitude' => 0.0,
            'longitude' => 0.0,
            'accuracy' => null,
            'status' => TrackingStatus::OFFLINE,
            'pinged_at' => null,
        ]);
        $ping->setRelation('user', $supervisor);
        $ping->setRelation('project', $project);

        return $ping;
    }

    /**
     * @return list<int>
     */
    public function accessibleProjectIds(User $user): array
    {
        if ($user->hasRole(Role::ADMIN->value)) {
            return Project::query()->pluck('id')->all();
        }

        if ($user->hasRole(Role::MANAGER->value)) {
            return Project::query()->where('manager_id', $user->id)->pluck('id')->all();
        }

        return [];
    }

    /**
     * @return Collection<int, Project>
     */
    public function accessibleProjects(User $user): Collection
    {
        if ($user->hasRole(Role::ADMIN->value)) {
            return Project::query()->orderBy('name')->get(['id', 'name', 'address']);
        }

        if ($user->hasRole(Role::MANAGER->value)) {
            return Project::query()->where('manager_id', $user->id)->orderBy('name')->get(['id', 'name', 'address']);
        }

        return new Collection;
    }
}
