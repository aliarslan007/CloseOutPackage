<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Controllers;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\PdfGeneration\Actions\GeneratePartialPdfAction;
use App\Domains\PdfGeneration\DTOs\GeneratePdfDTO;
use App\Domains\PdfGeneration\Enums\PdfType;
use App\Domains\PdfGeneration\Requests\GeneratePartialPdfRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class GeneratePartialPdfController extends Controller
{
    public function __construct(private readonly GeneratePartialPdfAction $generatePartialPdfAction) {}

    public function __invoke(GeneratePartialPdfRequest $request, CloseoutPackage $package): RedirectResponse
    {
        $emailTo = $request->validated('email_to');

        $this->generatePartialPdfAction->execute(
            new GeneratePdfDTO(
                closeoutPackageId: $package->id,
                type: PdfType::PARTIAL,
                generatedBy: $this->user()->id,
                emailTo: is_string($emailTo) ? $emailTo : null,
            ),
        );

        return redirect()->back()->with('success', 'Partial PDF generation started.');
    }
}
