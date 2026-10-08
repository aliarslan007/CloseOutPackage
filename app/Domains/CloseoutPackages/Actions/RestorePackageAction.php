<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Actions;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Projects\Actions\UpdateProjectProgressAction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class RestorePackageAction
{
    public function execute(CloseoutPackage $package): void
    {
        DB::transaction(function () use ($package): void {
            $project = $package->project;

            $package->restore();
            $package->update(['deleted_by' => null]);

            activity('security')
                ->causedBy(Auth::user())
                ->performedOn($package)
                ->event('package.restored')
                ->log('Package restored from trash');

            if ($project !== null) {
                app(UpdateProjectProgressAction::class)->execute($project);
            }
        });
    }
}
