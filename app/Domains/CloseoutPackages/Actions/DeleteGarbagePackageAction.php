<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Actions;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Actions\UpdateProjectProgressAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class DeleteGarbagePackageAction
{
    public function execute(CloseoutPackage $package): void
    {
        $project = $package->project;

        DB::transaction(function () use ($package, $project): void {
            $this->purge($package);

            if ($project !== null) {
                app(UpdateProjectProgressAction::class)->execute($project);
            }
        });
    }

    /**
     * Hard-delete the package and its photo files, letting the schema cascade
     * take its revisions, stages and notes with it. Split from execute() so the
     * project cascade can drop packages without rewriting progress and status on
     * a project that is itself being deleted.
     */
    public function purge(CloseoutPackage $package): void
    {
        $photos = Photo::query()->where('closeout_package_id', $package->id)->get();

        DB::transaction(function () use ($package, $photos): void {
            foreach ($photos as $photo) {
                try {
                    $photo->clearMediaCollection(Photo::COLLECTION);
                } catch (Throwable $exception) {
                    Log::error('Photo media cleanup failed during package purge', [
                        'package_id' => $package->id,
                        'photo_id' => $photo->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }

            $package->forceDelete();
        });

        activity('security')
            ->performedOn($package)
            ->event('package.purged')
            ->withProperties(['package_id' => $package->id])
            ->log('Garbage package hard-deleted');
    }
}
