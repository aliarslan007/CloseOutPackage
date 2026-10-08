<?php

declare(strict_types=1);

namespace App\Domains\EmailTemplates\DTOs;

use App\Domains\Email\Enums\EmailType;
use App\Domains\EmailTemplates\Enums\EmailTemplateStatus;

final readonly class UpdateEmailTemplateDTO
{
    public function __construct(
        public EmailType $emailType,
        public string $name,
        public string $subjectTemplate,
        public string $bodyTemplate,
        public EmailTemplateStatus $status = EmailTemplateStatus::ACTIVE,
        public bool $isDefault = false,
    ) {}
}
