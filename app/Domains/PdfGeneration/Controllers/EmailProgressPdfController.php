<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Controllers;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\PdfGeneration\Jobs\GeneratePartialPdfJob;
use App\Domains\PdfGeneration\Requests\SendProgressPdfEmailRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class EmailProgressPdfController extends Controller
{
    public function __invoke(SendProgressPdfEmailRequest $request, CloseoutPackage $package): RedirectResponse
    {
        /** @var string $emailTo */
        $emailTo = $request->validated('email_to');
        $templateId = $request->validated('email_template_id');
        $subjectOverride = $request->validated('subject_override');
        $bodyOverride = $request->validated('body_override');

        GeneratePartialPdfJob::dispatch(
            $package,
            $emailTo,
            is_numeric($templateId) ? (int) $templateId : null,
            is_string($subjectOverride) ? $subjectOverride : null,
            is_string($bodyOverride) ? $bodyOverride : null,
        );

        return redirect()->back()->with('success', 'Progress PDF generation queued.');
    }
}
