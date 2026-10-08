<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Controllers;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\PdfGeneration\Actions\GenerateFinalPdfAction;
use App\Domains\PdfGeneration\DTOs\GeneratePdfDTO;
use App\Domains\PdfGeneration\Enums\PdfType;
use App\Domains\PdfGeneration\Requests\GenerateFinalPdfRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class GenerateFinalPdfController extends Controller
{
    public function __construct(private readonly GenerateFinalPdfAction $generateFinalPdfAction) {}

    public function __invoke(GenerateFinalPdfRequest $request, CloseoutPackage $package): RedirectResponse
    {
        $this->generateFinalPdfAction->execute(
            new GeneratePdfDTO(
                closeoutPackageId: $package->id,
                type: PdfType::FINAL,
                generatedBy: $this->user()->id,
            ),
        );

        return redirect()->back()->with('success', 'Final PDF generation started.');
    }
}
