<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Controllers;

use App\Domains\CloseoutPackages\Queries\PackageQuery;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class GarbagePackageController extends Controller
{
    public function __construct(private readonly PackageQuery $packageQuery) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Packages/Deleted', [
            'packages' => $this->packageQuery->trashedForAdmin(),
        ]);
    }
}
