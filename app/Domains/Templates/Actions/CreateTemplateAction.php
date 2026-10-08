<?php

declare(strict_types=1);

namespace App\Domains\Templates\Actions;

use App\Domains\Markets\Models\Market;
use App\Domains\Templates\DTOs\CreateTemplateDTO;
use App\Domains\Templates\DTOs\CreateTemplateSectionDTO;
use App\Domains\Templates\Enums\StageType;
use App\Domains\Templates\Events\TemplateCreated;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Queries\TemplateQuery;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use App\Shared\Exceptions\MarketAccessDeniedException;
use App\Shared\Exceptions\TemplateNameAlreadyExistsException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class CreateTemplateAction
{
    private const DEFAULT_HEADINGS = [
        'pre_construction' => 'Pre Construction',
        'construction' => 'Construction / Installation',
        'post_completion' => 'Post Completion',
    ];

    public function execute(CreateTemplateDTO $dto, User $actor): Template
    {
        $market = Market::findOrFail($dto->marketId);

        if (! $actor->hasRole(Role::ADMIN->value) && ! $market->userHasAccess($actor)) {
            throw new MarketAccessDeniedException('You do not have access to this market.');
        }

        try {
            $template = DB::transaction(function () use ($dto, $actor): Template {
                $template = Template::create([
                    'market_id' => $dto->marketId,
                    'name' => $dto->name,
                    'description' => $dto->description,
                    'is_active' => $dto->isActive,
                    'created_by' => $actor->id,
                ]);

                $this->seedSections($template);

                TemplateCreated::dispatch($template);

                return $template;
            });
        } catch (UniqueConstraintViolationException) {
            throw new TemplateNameAlreadyExistsException('A template with this name already exists in this market.');
        }

        TemplateQuery::flushCache();

        return $template;
    }

    private function seedSections(Template $template): void
    {
        $sortOrder = 1;

        foreach (StageType::cases() as $stage) {
            app(CreateTemplateSectionAction::class)->execute(new CreateTemplateSectionDTO(
                templateId: $template->id,
                stage: $stage,
                heading: self::DEFAULT_HEADINGS[$stage->value],
                sortOrder: $sortOrder,
            ));

            $sortOrder++;
        }
    }
}
