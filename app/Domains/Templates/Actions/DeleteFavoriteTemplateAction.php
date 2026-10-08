<?php

declare(strict_types=1);

namespace App\Domains\Templates\Actions;

use App\Domains\Templates\Models\FavoriteTemplate;

final class DeleteFavoriteTemplateAction
{
    public function execute(FavoriteTemplate $favorite): void
    {
        $favorite->delete();
    }
}
