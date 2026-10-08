<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Actions;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Email\Actions\SendCopEmailAction;
use App\Domains\Email\Enums\EmailType;
use App\Domains\Email\Services\EmailComposerService;
use App\Domains\EmailTemplates\Models\EmailTemplate;
use App\Domains\EmailTemplates\Queries\EmailTemplateQuery;
use App\Domains\EmailTemplates\Services\EmailTemplateResolver;
use App\Domains\PdfGeneration\Models\PackageRevision;

final class EmailProgressPdfAction
{
    public function __construct(private readonly EmailTemplateResolver $resolver) {}

    /**
     * Sends the progress PDF package email. Applies the explicitly passed template
     * or automatically resolves the active default template for PROGRESS_PDF.
     */
    public function execute(
        CloseoutPackage $package,
        PackageRevision $revision,
        string $emailTo,
        ?EmailTemplate $template = null,
        ?string $subjectOverride = null,
        ?string $bodyOverride = null,
    ): void {
        $dto = app(EmailComposerService::class)->composeProgressPdf($package, $revision, $emailTo);

        $activeTemplate = $template ?? app(EmailTemplateQuery::class)->defaultForType(EmailType::PROGRESS_PDF);

        if ($activeTemplate !== null) {
            $dto = $this->resolver->resolve($dto, $activeTemplate, $subjectOverride, $bodyOverride, [
                'revision_label' => $revision->revision_label,
            ]);
        }

        app(SendCopEmailAction::class)->execute($dto);

        $revision->update([
            'emailed_at' => now(),
            'emailed_to' => $emailTo,
        ]);
    }
}
