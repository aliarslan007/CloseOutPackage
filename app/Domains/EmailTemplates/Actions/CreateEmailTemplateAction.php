<?php

declare(strict_types=1);

namespace App\Domains\EmailTemplates\Actions;

use App\Domains\EmailTemplates\DTOs\CreateEmailTemplateDTO;
use App\Domains\EmailTemplates\Models\EmailTemplate;
use App\Domains\Users\Models\User;

final class CreateEmailTemplateAction
{
    public function __construct(private readonly SetDefaultEmailTemplateAction $setDefault) {}

    public function execute(CreateEmailTemplateDTO $dto, User $actor): EmailTemplate
    {
        $template = EmailTemplate::create([
            'email_type' => $dto->emailType,
            'name' => $dto->name,
            'subject_template' => $dto->subjectTemplate,
            'body_template' => $dto->bodyTemplate,
            'status' => $dto->status,
            'is_default' => false,
            'created_by' => $actor->id,
        ]);

        if ($dto->isDefault) {
            $this->setDefault->execute($template);
        }

        return $template;
    }
}
