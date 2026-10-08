<?php

declare(strict_types=1);

namespace App\Domains\Templates\Actions;

use App\Domains\Templates\DTOs\UpdateTemplateDTO;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Queries\TemplateQuery;
use App\Shared\Exceptions\TemplateMarketMissingException;
use App\Shared\Exceptions\TemplateNameAlreadyExistsException;
use Illuminate\Database\UniqueConstraintViolationException;

final class UpdateTemplateAction
{
    public function execute(Template $template, UpdateTemplateDTO $dto): Template
    {
        $attributes = [];

        if ($dto->name !== null) {
            $attributes['name'] = $dto->name;
        }

        if ($dto->description !== null) {
            $attributes['description'] = $dto->description;
        }

        $marketMissing = $template->marketIsMissing();

        // A template whose market was deleted may be re-linked to a market; the
        // market of a healthy template stays fixed. Re-linking clears the
        // deactivation reason but does not activate it on its own.
        if ($dto->marketId !== null && $marketMissing) {
            $attributes['market_id'] = $dto->marketId;
            $attributes['inactive_reason'] = null;
            $marketMissing = false;
        }

        if ($dto->isActive !== null) {
            if ($dto->isActive && $marketMissing) {
                throw new TemplateMarketMissingException('Link this template to a market before activating it.');
            }

            $attributes['is_active'] = $dto->isActive;
        }

        if ($attributes !== []) {
            try {
                $template->update($attributes);
            } catch (UniqueConstraintViolationException) {
                throw new TemplateNameAlreadyExistsException('A template with this name already exists in this market.');
            }
        }

        TemplateQuery::flushCache();

        return $template;
    }
}
