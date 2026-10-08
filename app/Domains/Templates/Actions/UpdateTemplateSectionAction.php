<?php

declare(strict_types=1);

namespace App\Domains\Templates\Actions;

use App\Domains\Templates\Models\TemplateSection;

final class UpdateTemplateSectionAction
{
    public function execute(TemplateSection $section, string $heading, ?string $adminNotes): TemplateSection
    {
        $section->update([
            'heading' => $heading,
            'admin_notes' => $adminNotes,
        ]);

        return $section;
    }
}
