<?php

declare(strict_types=1);

namespace App\Domains\Templates\Actions;

use App\Domains\Templates\Events\TemplateDuplicated;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Queries\TemplateQuery;
use App\Domains\Templates\Services\TemplateCloningService;

final class DuplicateTemplateAction
{
    public function __construct(private readonly TemplateCloningService $templateCloningService) {}

    public function execute(Template $template, ?int $marketId = null, ?string $name = null): Template
    {
        $copy = $this->templateCloningService->duplicate($template, $marketId, $name);

        TemplateDuplicated::dispatch($template, $copy);

        TemplateQuery::flushCache();

        return $copy;
    }
}
