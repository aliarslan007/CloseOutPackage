<?php

declare(strict_types=1);

namespace App\Domains\Templates\Actions;

use App\Domains\Templates\DTOs\SaveFavoriteTemplateDTO;
use App\Domains\Templates\Models\FavoriteTemplate;

final class SaveFavoriteTemplateAction
{
    public function execute(SaveFavoriteTemplateDTO $dto): FavoriteTemplate
    {
        return FavoriteTemplate::create([
            'user_id' => $dto->userId,
            'template_id' => $dto->templateId,
            'name' => $dto->name,
            'snapshot' => $dto->snapshot,
        ]);
    }
}
