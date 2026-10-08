<?php

declare(strict_types=1);

namespace App\Domains\Projects\Actions;

use App\Domains\Projects\Events\FieldSupervisorRemoved;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class RemoveFieldSupervisorAction
{
    public function execute(Project $project, User $user, ?User $actor = null): void
    {
        $actor ??= Auth::user();

        DB::transaction(function () use ($project, $user, $actor): void {
            $project->fieldSupervisors()->detach($user->id);

            // A supervisor who is no longer assigned cannot remain the primary.
            if ((int) $project->primary_supervisor_id === (int) $user->id) {
                $project->update(['primary_supervisor_id' => null]);
            }

            activity('project')
                ->causedBy($actor)
                ->performedOn($project)
                ->event('supervisor.removed')
                ->withProperties(['user_id' => $user->id])
                ->log('Field supervisor removed from project');
        });

        FieldSupervisorRemoved::dispatch($project, $user, $actor);
    }
}
