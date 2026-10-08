<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Controllers;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Queries\PackageStageQuery;
use App\Domains\Templates\Enums\StageType;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class PackageStageController extends Controller
{
    public function __construct(private readonly PackageStageQuery $packageStageQuery) {}

    public function show(CloseoutPackage $package, StageType $stage): Response
    {
        $this->authorize('view', $package);

        return Inertia::render('Packages/Stage', [
            'package' => $package,
            'stage' => $this->packageStageQuery->show($package, $stage),
        ]);
    }
}
