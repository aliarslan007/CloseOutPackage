<?php

declare(strict_types=1);

namespace App\Domains\Templates\Actions;

use App\Domains\Templates\Models\Template;

final class UpdateTemplateAdditionalNotesAction
{
    public function execute(Template $template, ?string $notes): void
    {
        $template->additional_site_notes = $notes !== null ? trim($notes) : null;

        if (! $template->isDirty('additional_site_notes')) {
            return;
        }

        $template->save();
    }
}
