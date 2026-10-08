<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Controllers;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Queries\PackageQuery;
use App\Domains\EmailTemplates\Models\EmailTemplate;
use App\Domains\PdfGeneration\Actions\EmailFinalPdfAction;
use App\Domains\PdfGeneration\Requests\EmailFinalPdfRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class EmailFinalPdfController extends Controller
{
    public function __construct(
        private readonly PackageQuery $packageQuery,
        private readonly EmailFinalPdfAction $emailFinalPdfAction,
    ) {}

    public function __invoke(EmailFinalPdfRequest $request, CloseoutPackage $package): RedirectResponse
    {
        $revision = $this->packageQuery->latestFinalRevision($package);

        abort_unless($revision !== null, 404);

        $emailTo = $request->validated('email_to');

        if (! is_string($emailTo) || $emailTo === '') {
            $project = $package->project()->with('customerEngineer')->first();

            // Prefer the assigned customer engineer, falling back to the legacy
            // project column (which itself falls back to the global default).
            $emailTo = $project?->customerEngineer?->email ?? $project?->vzw_engineer_email;
        }

        if (! is_string($emailTo) || $emailTo === '') {
            return redirect()->back()->with('error', 'No recipient email is available for this package.');
        }

        $subjectOverride = $request->validated('subject_override');
        $bodyOverride = $request->validated('body_override');

        $this->emailFinalPdfAction->execute(
            $revision,
            $emailTo,
            $this->templateFrom($request),
            is_string($subjectOverride) ? $subjectOverride : null,
            is_string($bodyOverride) ? $bodyOverride : null,
        );

        return redirect()->back()->with('success', 'Final PDF email queued.');
    }

    private function templateFrom(EmailFinalPdfRequest $request): ?EmailTemplate
    {
        $templateId = $request->validated('email_template_id');

        return $templateId !== null ? EmailTemplate::query()->findOrFail((int) $templateId) : null;
    }
}
