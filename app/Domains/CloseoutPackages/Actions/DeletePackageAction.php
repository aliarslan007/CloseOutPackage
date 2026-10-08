<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Actions;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Projects\Actions\UpdateProjectProgressAction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class DeletePackageAction
{
    public function execute(CloseoutPackage $package): void
    {
        DB::transaction(function () use ($package): void {
            $project = $package->project;

            $package->update(['deleted_by' => Auth::id()]);
            $package->delete();

            activity('security')
                ->causedBy(Auth::user())
                ->performedOn($package)
                ->event('package.deleted')
                ->withProperties(['status' => $package->status])
                ->log('Package soft-deleted');

            if ($project !== null) {
                app(UpdateProjectProgressAction::class)->execute($project);
            }
        });
    }
}
