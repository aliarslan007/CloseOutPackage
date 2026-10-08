<?php

declare(strict_types=1);

namespace App\Domains\Projects\Actions;

use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Events\FieldSupervisorAssigned;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Exceptions\MarketAccessDeniedException;
use Illuminate\Support\Facades\DB;

final class AssignFieldSupervisorAction
{
    public function execute(Project $project, int $userId, ?User $assignedBy): void
    {
        $user = User::findOrFail($userId);

        if (! Market::findOrFail($project->market_id)->userHasAccess($user)) {
            throw new MarketAccessDeniedException('The selected user does not have access to this market.');
        }

        if ($project->fieldSupervisors()->whereKey($user->id)->exists()) {
            return;
        }

        DB::transaction(function () use ($project, $user, $assignedBy): void {
            $project->fieldSupervisors()->attach($user->id, ['assigned_by' => $assignedBy?->id]);

            activity('project')
                ->causedBy($assignedBy)
                ->performedOn($project)
                ->event('supervisor.assigned')
                ->withProperties(['user_id' => $user->id])
                ->log('Field supervisor assigned to project');
        });

        FieldSupervisorAssigned::dispatch($project, $user, $assignedBy);
    }
}
