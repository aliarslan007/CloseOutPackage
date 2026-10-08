<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Controllers;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\PdfGeneration\Enums\PdfRevisionStatus;
use App\Domains\PdfGeneration\Models\PackageRevision;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PdfStatusController extends Controller
{
    public function __invoke(Request $request, CloseoutPackage $package): Response|JsonResponse
    {
        $this->authorize('download', [PackageRevision::class, $package]);

        /** @var PackageRevision|null $revision */
        $revision = PackageRevision::query()
            ->where('closeout_package_id', $package->id)
            ->latest('id')
            ->first();

        $status = $revision?->status ?? ($package->final_pdf_path !== null ? PdfRevisionStatus::Ready : null);
        $isReady = $status === PdfRevisionStatus::Ready || ($status === null && $package->final_pdf_path !== null);
        $isFailed = $status === PdfRevisionStatus::Failed;
        $isGenerating = $status === PdfRevisionStatus::Pending;

        $progress = match (true) {
            $isReady => 100,
            $isFailed => 0,
            $isGenerating => 10,
            default => 0,
        };

        $payload = [
            'package' => $package,
            'revision' => $revision,
            'isReady' => $isReady,
            'isGenerating' => $isGenerating,
            'isFailed' => $isFailed,
            'progress' => $progress,
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('Packages/PdfStatus', $payload);
    }
}
