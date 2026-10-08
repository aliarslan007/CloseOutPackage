<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Queries;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\Templates\Enums\StageType;

final class PackageStageQuery
{
    public function show(CloseoutPackage $package, StageType $stage): PackageStage
    {
        return $package->stages()
            ->where('stage', $stage)
            ->with([
                'categorySelections.templatePictureCategory',
                'package.template.sections',
            ])
            ->firstOrFail();
    }
}
