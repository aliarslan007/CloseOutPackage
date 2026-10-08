<?php

declare(strict_types=1);

namespace App\Domains\Templates\Actions;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Queries\TemplateQuery;
use App\Shared\Exceptions\TemplateInUseException;

final class DeleteTemplateAction
{
    public function execute(Template $template): void
    {
        // Only ACTIVE packages block deletion — soft-deleted (trashed) ones,
        // and packages belonging to trashed projects, must not pin a template forever.
        $inUse = CloseoutPackage::query()
            ->where('template_id', $template->id)
            ->whereNull('closeout_packages.deleted_at')
            ->whereHas('project', fn ($q) => $q->whereNull('projects.deleted_at'))
            ->exists();

        if ($inUse) {
            throw new TemplateInUseException('This template is used by close-out packages and cannot be deleted.');
        }

        $template->delete();

        TemplateQuery::flushCache();
    }
}
