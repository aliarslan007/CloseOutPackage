<?php

declare(strict_types=1);

namespace App\Domains\EmailTemplates\Actions;

use App\Domains\EmailTemplates\DTOs\UpdateEmailTemplateDTO;
use App\Domains\EmailTemplates\Models\EmailTemplate;

final class UpdateEmailTemplateAction
{
    public function __construct(private readonly SetDefaultEmailTemplateAction $setDefault) {}

    public function execute(EmailTemplate $template, UpdateEmailTemplateDTO $dto): EmailTemplate
    {
        // PUT semantics: the form state is the truth, so "not default" here
        // also demotes a previously-default template.
        $template->update([
            'email_type' => $dto->emailType,
            'name' => $dto->name,
            'subject_template' => $dto->subjectTemplate,
            'body_template' => $dto->bodyTemplate,
            'status' => $dto->status,
            'is_default' => false,
        ]);

        if ($dto->isDefault) {
            $this->setDefault->execute($template);
        }

        return $template->refresh();
    }
}
