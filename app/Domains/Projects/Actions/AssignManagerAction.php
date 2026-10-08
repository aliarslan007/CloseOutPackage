<?php

declare(strict_types=1);

namespace App\Domains\Projects\Actions;

use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Events\ProjectManagerAssigned;
use App\Domains\Projects\Events\ProjectManagerRemoved;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use App\Shared\Exceptions\MarketAccessDeniedException;

final class AssignManagerAction
{
    public function execute(Project $project, int $managerId, ?User $actor): void
    {
        $manager = User::findOrFail($managerId);

        if (! $manager->hasRole(Role::MANAGER->value)) {
            throw new MarketAccessDeniedException('The selected user is not a manager.');
        }

        if (! Market::findOrFail($project->market_id)->userHasAccess($manager)) {
            throw new MarketAccessDeniedException('The selected manager does not have access to this market.');
        }

        $previousManager = $project->manager;

        $project->update(['manager_id' => $manager->id]);

        activity('project')
            ->causedBy($actor)
            ->performedOn($project)
            ->event('manager.assigned')
            ->withProperties(['manager_id' => $manager->id])
            ->log('Manager assigned to project');

        if ($previousManager !== null && $previousManager->id !== $manager->id) {
            ProjectManagerRemoved::dispatch($project, $previousManager);
        }

        if ($previousManager === null || $previousManager->id !== $manager->id) {
            ProjectManagerAssigned::dispatch($project, $manager);
        }
    }
}
