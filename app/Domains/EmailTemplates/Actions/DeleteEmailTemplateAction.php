<?php

declare(strict_types=1);

namespace App\Domains\EmailTemplates\Actions;

use App\Domains\EmailTemplates\Models\EmailTemplate;

final class DeleteEmailTemplateAction
{
    public function execute(EmailTemplate $template): void
    {
        $template->delete();
    }
}
