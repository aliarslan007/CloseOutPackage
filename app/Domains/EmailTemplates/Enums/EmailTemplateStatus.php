<?php

declare(strict_types=1);

namespace App\Domains\EmailTemplates\Enums;

enum EmailTemplateStatus: string
{
    case ACTIVE = 'active';
    case DRAFT = 'draft';
    case ARCHIVED = 'archived';
}
