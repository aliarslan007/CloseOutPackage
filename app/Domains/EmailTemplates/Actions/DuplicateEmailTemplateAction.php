<?php

declare(strict_types=1);

namespace App\Domains\EmailTemplates\Actions;

use App\Domains\EmailTemplates\Enums\EmailTemplateStatus;
use App\Domains\EmailTemplates\Models\EmailTemplate;
use App\Domains\Users\Models\User;

final class DuplicateEmailTemplateAction
{
    public function execute(EmailTemplate $template, User $actor): EmailTemplate
    {
        $copy = $template->replicate();

        $copy->name = $template->name.' Copy';
        $copy->status = EmailTemplateStatus::DRAFT;
        $copy->is_default = false;
        $copy->created_by = $actor->id;
        $copy->save();

        return $copy;
    }
}
