<?php

declare(strict_types=1);

namespace App\Domains\Photos\Queries;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\Photos\Models\Photo;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class PhotoQuery
{
    /**
     * @return LengthAwarePaginator<int, Photo>
     */
    public function forStage(CloseoutPackage $package, PackageStage $stage): LengthAwarePaginator
    {
        return Photo::query()
            ->where('closeout_package_id', $package->id)
            ->where('package_stage_id', $stage->id)
            ->with(['categorySelection.templatePictureCategory', 'uploader'])
            ->latest()
            ->paginate(20);
    }

    public function media(Photo $photo): ?Media
    {
        return $photo->getFirstMedia(Photo::COLLECTION);
    }
}
