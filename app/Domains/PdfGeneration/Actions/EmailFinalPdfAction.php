<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Actions;

use App\Domains\Email\Actions\SendCopEmailAction;
use App\Domains\Email\Enums\EmailType;
use App\Domains\Email\Services\EmailComposerService;
use App\Domains\EmailTemplates\Models\EmailTemplate;
use App\Domains\EmailTemplates\Queries\EmailTemplateQuery;
use App\Domains\EmailTemplates\Services\EmailTemplateResolver;
use App\Domains\PdfGeneration\Models\PackageRevision;

final class EmailFinalPdfAction
{
    public function __construct(private readonly EmailTemplateResolver $resolver) {}

    /**
     * Sends the final PDF package email. Applies the explicitly passed template
     * or automatically resolves the active default template for FINAL_PDF_EMAIL.
     */
    public function execute(
        PackageRevision $revision,
        string $emailTo,
        ?EmailTemplate $template = null,
        ?string $subjectOverride = null,
        ?string $bodyOverride = null,
    ): void {
        $dto = app(EmailComposerService::class)->composeFinalPdfEmail($revision, $emailTo);

        $activeTemplate = $template ?? app(EmailTemplateQuery::class)->defaultForType(EmailType::FINAL_PDF_EMAIL);

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
